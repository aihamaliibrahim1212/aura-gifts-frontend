<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserToken;
use App\Models\PasswordResetToken;
use App\Models\SavedCart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserAuthController extends Controller
{
    // ── Response helpers ────────────────────────────────────────────────

    private function ok($data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function err(string $msg, int $status = 400)
    {
        return response()->json(['success' => false, 'error' => $msg], $status);
    }

    // ── Token generation & storage ──────────────────────────────────────

    private function issueToken(User $user, bool $remember = false): string
    {
        $token = bin2hex(random_bytes(32));
        $expiry = $remember ? now()->addDays(30) : now()->addHours(12);

        UserToken::create([
            'user_id'    => $user->id,
            'token'      => $token,
            'remember'   => $remember,
            'expires_at' => $expiry,
        ]);

        // Purge expired tokens for this user
        UserToken::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        return $token;
    }

    private function currentUser(): ?User
    {
        $id = request()->get('_user_id');
        return $id ? User::find($id) : null;
    }

    // ── POST /api/user/register ─────────────────────────────────────────

    public function register(Request $request)
    {
        $data = $request->json()->all();

        $name     = trim((string) ($data['name'] ?? ''));
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!$name || strlen($name) > 200) {
            return $this->err('Name is required (max 200 characters)');
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
            return $this->err('A valid email address is required');
        }
        if (strlen($password) < 8 || strlen($password) > 255) {
            return $this->err('Password must be 8–255 characters');
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->err('Password must contain at least one uppercase letter and one number');
        }

        if (User::where('email', $email)->exists()) {
            // Return a generic message to avoid email enumeration
            return $this->err('An account with this email already exists. Try logging in or use "Forgot Password".');
        }

        $verifyToken = bin2hex(random_bytes(32));

        $user = User::create([
            'name'               => $name,
            'email'              => $email,
            'password_hash'      => Hash::make($password),
            'provider'           => 'email',
            'role'               => 'customer',
            'email_verified'     => false,
            'email_verify_token' => $verifyToken,
        ]);

        // Send verification email (best-effort — don't fail registration if mail errors)
        $this->sendVerificationEmail($user, $verifyToken);

        $remember = !empty($data['remember']);
        $token = $this->issueToken($user, $remember);

        return $this->ok([
            'token' => $token,
            'user'  => $user->fresh()->toApiArray(),
        ], 201);
    }

    // ── POST /api/user/login ────────────────────────────────────────────

    public function login(Request $request)
    {
        $data = $request->json()->all();

        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $remember = !empty($data['remember']);

        if (!$email || !$password) {
            return $this->err('Email and password are required');
        }
        if (strlen($email) > 200 || strlen($password) > 255) {
            return $this->err('Invalid credentials', 401);
        }

        $user = User::where('email', $email)->first();

        // Use constant-time failure path to prevent timing attacks
        if (!$user || !$user->password_hash) {
            // Dummy check to keep timing consistent
            Hash::check('dummy', '$2y$12$dummyhashvaluefortimingequalityxxxx');
            return $this->err('Invalid email or password', 401);
        }

        if (!Hash::check($password, $user->password_hash)) {
            return $this->err('Invalid email or password', 401);
        }

        if (!$user->is_active) {
            return $this->err('This account has been disabled. Please contact support.', 403);
        }

        $token = $this->issueToken($user, $remember);

        return $this->ok([
            'token' => $token,
            'user'  => $user->fresh()->toApiArray(),
        ]);
    }

    // ── POST /api/user/logout ───────────────────────────────────────────

    public function logout(Request $request)
    {
        $bearer = $request->bearerToken() ?? $request->cookie('user_token');
        if ($bearer) {
            UserToken::where('token', $bearer)->delete();
        }
        return $this->ok(['message' => 'Logged out']);
    }

    // ── GET /api/user/me ────────────────────────────────────────────────

    public function me(Request $request)
    {
        // Re-validate token for /me (it's not behind middleware so anyone can probe)
        $token = $request->bearerToken() ?? $request->cookie('user_token');
        if (!$token) {
            return $this->err('Unauthorized', 401);
        }
        $tokenRecord = UserToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        if (!$tokenRecord) {
            return $this->err('Unauthorized', 401);
        }
        $user = User::find($tokenRecord->user_id);
        if (!$user || !$user->is_active) {
            return $this->err('Unauthorized', 401);
        }
        return $this->ok($user->toApiArray());
    }

    // ── POST /api/user/google ───────────────────────────────────────────
    // Accepts a Google ID token from the frontend (after Google One Tap / OAuth popup)
    // and exchanges it for a Aura user token.

    public function googleAuth(Request $request)
    {
        $data       = $request->json()->all();
        $idToken    = trim((string) ($data['id_token'] ?? ''));
        $remember   = !empty($data['remember']);

        if (!$idToken || strlen($idToken) > 4096) {
            return $this->err('Google ID token is required');
        }

        // Verify the Google ID token against Google's tokeninfo endpoint
        $googlePayload = $this->verifyGoogleToken($idToken);
        if (!$googlePayload) {
            return $this->err('Invalid or expired Google token', 401);
        }

        $googleClientId = config('services.google.client_id');
        if ($googleClientId && ($googlePayload['aud'] ?? '') !== $googleClientId) {
            return $this->err('Token audience mismatch', 401);
        }

        $googleId = (string) ($googlePayload['sub'] ?? '');
        $email    = strtolower(trim((string) ($googlePayload['email'] ?? '')));
        $name     = trim((string) ($googlePayload['name'] ?? ''));
        $avatar   = (string) ($googlePayload['picture'] ?? '');

        if (!$googleId || !$email) {
            return $this->err('Incomplete Google profile', 400);
        }

        // 1. Try to find existing user by google_id
        $user = User::where('google_id', $googleId)->first();

        // 2. Try to link to existing email account
        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                // Link this Google account to the existing email account
                $user->google_id      = $googleId;
                $user->email_verified = true;
                // Re-enable account if it was disabled (e.g. from schema default issue)
                if (!$user->is_active) {
                    $user->is_active = true;
                }
                // Store avatar in Cloudinary for instant loading (no Google no-cache headers)
                if ($avatar) {
                    $stored = $this->storeGoogleAvatar($avatar);
                    $user->avatar_url = $stored ?: $avatar;
                }
                // Always mark as google provider since that's how they log in
                $user->provider = 'google';
                $user->save();
            }
        }

        // 3. Auto-create a new account for first-time Google users
        if (!$user) {
            // Create the user immediately using the Google URL directly.
            // Avatar migration to Cloudinary happens in the background after we
            // respond, so the first sign-up is never slow or error-prone.
            $user = User::create([
                'name'           => $name ?: explode('@', $email)[0],
                'email'          => $email,
                'password_hash'  => null,
                'avatar_url'     => $avatar ?: null,
                'provider'       => 'google',
                'google_id'      => $googleId,
                'role'           => 'customer',
                'email_verified' => true,
            ]);

            // Migrate avatar to Cloudinary asynchronously (best-effort, won't block login)
            if ($avatar) {
                $userId = $user->id;
                register_shutdown_function(function() use ($userId, $avatar) {
                    try {
                        $stored = $this->storeGoogleAvatar($avatar);
                        if ($stored) {
                            User::where('id', $userId)->update(['avatar_url' => $stored]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Background avatar migration failed: ' . $e->getMessage());
                    }
                });
            }
        }

        if (!$user->is_active) {
            // Re-enable — Google sign-in proves account ownership
            $user->is_active = true;
            $user->save();
        }

        $token = $this->issueToken($user, $remember);

        return $this->ok([
            'token' => $token,
            'user'  => $user->fresh()->toApiArray(),
        ]);
    }

    // ── POST /api/user/forgot-password ─────────────────────────────────

    public function forgotPassword(Request $request)
    {
        $data  = $request->json()->all();
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
            return $this->err('A valid email address is required');
        }

        // Always return the same message to prevent email enumeration
        $user = User::where('email', $email)->first();
        if ($user && $user->provider !== 'google') {
            $resetToken = bin2hex(random_bytes(32));

            // Invalidate any existing tokens for this email
            PasswordResetToken::where('email', $email)->delete();

            PasswordResetToken::create([
                'email'      => $email,
                'token'      => $resetToken,
                'expires_at' => now()->addHour(),
            ]);

            $this->sendPasswordResetEmail($email, $user->name, $resetToken);
        }

        return $this->ok(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    // ── POST /api/user/reset-password ──────────────────────────────────

    public function resetPassword(Request $request)
    {
        $data        = $request->json()->all();
        $token       = trim((string) ($data['token'] ?? ''));
        $newPassword = (string) ($data['password'] ?? '');

        if (!$token || strlen($token) > 64) {
            return $this->err('Invalid reset token');
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 255) {
            return $this->err('Password must be 8–255 characters');
        }
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return $this->err('Password must contain at least one uppercase letter and one number');
        }

        $record = PasswordResetToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return $this->err('This reset link is invalid or has expired. Please request a new one.', 410);
        }

        $user = User::where('email', $record->email)->first();
        if (!$user) {
            return $this->err('Account not found', 404);
        }

        $user->password_hash = Hash::make($newPassword);
        $user->save();

        // Invalidate all existing sessions after password change
        UserToken::where('user_id', $user->id)->delete();
        PasswordResetToken::where('email', $record->email)->delete();

        return $this->ok(['message' => 'Password updated successfully. You can now log in.']);
    }

    // ── GET /api/user/verify-email ──────────────────────────────────────

    public function verifyEmail(Request $request)
    {
        $token = trim((string) ($request->query('token', '')));

        if (!$token || strlen($token) > 64) {
            return $this->err('Invalid verification token', 400);
        }

        $user = User::where('email_verify_token', $token)->first();
        if (!$user) {
            return $this->err('Invalid or already used verification link', 400);
        }

        $user->email_verified     = true;
        $user->email_verify_token = null;
        $user->save();

        return $this->ok(['message' => 'Email verified successfully. You can now log in.']);
    }

    // ── PUT /api/user/profile ─────────────────────────────────────────── (requires auth)

    public function updateProfile(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) return $this->err('Unauthorized', 401);

        $data = $request->json()->all();

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if (!$name || strlen($name) > 200) {
                return $this->err('Name must be 1–200 characters');
            }
            $user->name = $name;
        }

        if (isset($data['avatar_url'])) {
            $url = trim((string) $data['avatar_url']);
            if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->err('Invalid avatar URL');
            }
            $user->avatar_url = $url ?: null;
        }

        // Delivery fields
        $deliveryFields = ['phone', 'island', 'address', 'boat_name', 'boat_number'];
        $deliveryLimits = ['phone' => 30, 'island' => 100, 'address' => 300, 'boat_name' => 100, 'boat_number' => 50];
        foreach ($deliveryFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = trim((string) ($data[$field] ?? ''));
                $max = $deliveryLimits[$field];
                if ($val && strlen($val) > $max) {
                    return $this->err(ucfirst(str_replace('_', ' ', $field)) . " must be under {$max} characters");
                }
                $user->$field = $val ?: null;
            }
        }

        // Password change — requires current password unless Google-only user
        if (isset($data['new_password'])) {
            $newPw = (string) $data['new_password'];
            if (strlen($newPw) < 8 || strlen($newPw) > 255) {
                return $this->err('New password must be 8–255 characters');
            }
            if (!preg_match('/[A-Z]/', $newPw) || !preg_match('/[0-9]/', $newPw)) {
                return $this->err('Password must contain at least one uppercase letter and one number');
            }

            if ($user->password_hash) {
                // Has an existing password — require current_password
                $currentPw = (string) ($data['current_password'] ?? '');
                if (!$currentPw || !Hash::check($currentPw, $user->password_hash)) {
                    return $this->err('Current password is incorrect', 403);
                }
            }

            $user->password_hash = Hash::make($newPw);

            // Revoke all other sessions so other devices are logged out after PW change
            $currentToken = $request->bearerToken() ?? $request->cookie('user_token');
            UserToken::where('user_id', $user->id)
                ->where('token', '!=', $currentToken)
                ->delete();
        }

        $user->save();
        return $this->ok($user->toApiArray());
    }

    // ── GET /api/user/orders ─────────────────────────────────────────── (requires auth)

    public function orderHistory(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) return $this->err('Unauthorized', 401);

        $orders = Order::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->ok($orders->map->toApiArray()->values());
    }

    // ── GET /api/user/cart ───────────────────────────────────────────── (requires auth)

    public function getCart(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) return $this->err('Unauthorized', 401);

        $saved = SavedCart::where('user_id', $user->id)->first();
        return $this->ok(['items' => $saved ? ($saved->items ?? []) : []]);
    }

    // ── PUT /api/user/cart ───────────────────────────────────────────── (requires auth)

    public function saveCart(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) return $this->err('Unauthorized', 401);

        $data  = $request->json()->all();
        $items = $data['items'] ?? [];

        if (!is_array($items)) {
            return $this->err('Items must be an array');
        }

        // Sanitise each item — only keep safe fields
        $clean = array_map(function ($item) {
            return [
                'index' => (int) ($item['index'] ?? 0),
                'name'  => substr((string) ($item['name'] ?? ''), 0, 200),
                'price' => substr((string) ($item['price'] ?? ''), 0, 30),
                'qty'   => max(1, (int) ($item['qty'] ?? 1)),
                'img'   => substr((string) ($item['img'] ?? ''), 0, 500),
                'stock' => isset($item['stock']) ? (int) $item['stock'] : null,
            ];
        }, array_slice($items, 0, 100)); // cap at 100 items

        SavedCart::updateOrCreate(
            ['user_id' => $user->id],
            ['items' => $clean]
        );

        return $this->ok(['message' => 'Cart saved', 'items' => $clean]);
    }

    // ── DELETE /api/user/account ─────────────────────────────────────── (requires auth)

    public function deleteAccount(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) return $this->err('Unauthorized', 401);

        $data = $request->json()->all();

        // Require password confirmation for email accounts
        if ($user->password_hash) {
            $password = (string) ($data['password'] ?? '');
            if (!$password || !Hash::check($password, $user->password_hash)) {
                return $this->err('Incorrect password', 403);
            }
        }

        // Anonymise orders before deleting the account
        Order::where('user_id', $user->id)->update(['user_id' => null]);

        $user->delete();

        return $this->ok(['message' => 'Account deleted successfully']);
    }

    // ── GET /api/user/admin/users ────────────────────────────────────── (superadmin only)

    public function adminListUsers(Request $request)
    {
        $user = $this->currentUser();
        if (!$user || $user->role !== 'superadmin') {
            return $this->err('Superadmin access required', 403);
        }

        $users = User::orderByDesc('created_at')->get();
        return $this->ok($users->map(function($u) {
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'avatar_url'     => $u->avatar_url,
                'provider'       => $u->provider,
                'role'           => $u->role,
                'email_verified' => (bool) $u->email_verified,
                'is_active'      => (bool) $u->is_active,
                'created_at'     => $u->created_at?->toISOString(),
            ];
        })->values());
    }

    // ── PUT /api/user/admin/users/{id}/role ──────────────────────────── (superadmin only)

    public function adminSetUserRole(Request $request, int $id)
    {
        $requester = $this->currentUser();
        if (!$requester || $requester->role !== 'superadmin') {
            return $this->err('Superadmin access required', 403);
        }

        $target = User::find($id);
        if (!$target) return $this->err('User not found', 404);

        $data = $request->json()->all();
        $role = $data['role'] ?? null;
        $validRoles = ['customer', 'superadmin'];

        if (!$role || !in_array($role, $validRoles)) {
            return $this->err('Role must be one of: customer, admin, superadmin');
        }

        // Prevent revoking your own superadmin
        if ($requester->id === $id && $role !== 'superadmin') {
            return $this->err('You cannot remove your own superadmin role');
        }

        $target->role = $role;
        $target->save();

        return $this->ok($target->toApiArray());
    }

    // ── Email helpers ────────────────────────────────────────────────────

    private function sendVerificationEmail(User $user, string $token): void
    {
        try {
            $appUrl      = config('app.url');
            $verifyUrl   = $appUrl . '/pages/verify-email.html?token=' . $token;
            $fromAddress = config('mail.from.address', 'noreply@aura.gifts');
            $fromName    = config('mail.from.name', 'Aura Gifts');

            Mail::raw(
                "Hi {$user->name},\n\n" .
                "Welcome to Aura Gifts! Please verify your email address by clicking the link below:\n\n" .
                $verifyUrl . "\n\n" .
                "This link expires in 24 hours. If you did not create an account, you can safely ignore this email.\n\n" .
                "— Aura Gifts",
                function ($message) use ($user, $fromAddress, $fromName) {
                    $message->to($user->email, $user->name)
                            ->from($fromAddress, $fromName)
                            ->subject('Verify your Aura Gifts account');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Could not send verification email: ' . $e->getMessage());
        }
    }

    private function sendPasswordResetEmail(string $email, string $name, string $token): void
    {
        try {
            $appUrl      = config('app.url');
            $resetUrl    = $appUrl . '/pages/reset-password.html?token=' . $token;
            $fromAddress = config('mail.from.address', 'noreply@aura.gifts');
            $fromName    = config('mail.from.name', 'Aura Gifts');

            Mail::raw(
                "Hi {$name},\n\n" .
                "We received a request to reset your Aura Gifts password. Click the link below to create a new password:\n\n" .
                $resetUrl . "\n\n" .
                "This link expires in 1 hour. If you did not request a password reset, you can safely ignore this email.\n\n" .
                "— Aura Gifts",
                function ($message) use ($email, $name, $fromAddress, $fromName) {
                    $message->to($email, $name)
                            ->from($fromAddress, $fromName)
                            ->subject('Reset your Aura Gifts password');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Could not send password reset email: ' . $e->getMessage());
        }
    }

    // ── Store Google avatar in Cloudinary ─────────────────────────────
    // Downloads the Google profile picture and uploads it to Cloudinary
    // so we serve it from our own CDN with proper cache headers.
    private function storeGoogleAvatar(string $googleAvatarUrl): ?string
    {
        try {
            // Request a larger size from Google (replace s96-c with s200-c)
            $url = preg_replace('/=s\d+-c$/', '=s200-c', $googleAvatarUrl);

            $cloudName  = config('cloudinary.cloud_name');
            $apiKey     = config('cloudinary.api_key');
            $apiSecret  = config('cloudinary.api_secret');

            if (!$cloudName || !$apiKey || !$apiSecret) {
                return null; // Cloudinary not configured — fall back to Google URL
            }

            $cloudinary = new \Cloudinary\Cloudinary(
                \Cloudinary\Configuration\Configuration::instance([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key'    => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                    'url' => ['secure' => true],
                ])
            );

            $result = $cloudinary->uploadApi()->upload($url, [
                'folder'         => 'aura-gifts/avatars',
                'transformation' => [['width' => 200, 'height' => 200, 'crop' => 'fill', 'gravity' => 'face']],
                'format'         => 'jpg',
                'quality'        => 'auto',
            ]);

            return $result['secure_url'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Could not store Google avatar in Cloudinary: ' . $e->getMessage());
            return null;
        }
    }

    // ── Google token verification ─────────────────────────────────────
    private function verifyGoogleToken(string $idToken): ?array
    {
        // Use Google's tokeninfo endpoint — simple, no extra library required.
        // For production, consider using Google's PHP client library for offline
        // verification without the extra HTTP round-trip.
        try {
            $url     = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
            $context = stream_context_create([
                'http' => [
                    'timeout'         => 5,
                    'follow_location' => 1,
                    'method'          => 'GET',
                ],
                'ssl' => ['verify_peer' => true],
            ]);

            $raw = @file_get_contents($url, false, $context);
            if (!$raw) return null;

            $payload = json_decode($raw, true);
            if (!is_array($payload)) return null;

            // Must have a subject (user ID) and email
            if (empty($payload['sub']) || empty($payload['email'])) return null;

            // Check token is not expired (Google also checks this, but belt-and-suspenders)
            if (!empty($payload['exp']) && (int) $payload['exp'] < time()) return null;

            return $payload;
        } catch (\Throwable $e) {
            Log::warning('Google token verification failed: ' . $e->getMessage());
            return null;
        }
    }
}
