<?php

namespace App\Http\Middleware;

use App\Models\UserToken;
use Closure;
use Illuminate\Http\Request;

/**
 * Protects admin panel routes using the customer user token system.
 * Requires the user to have role 'admin' or 'superadmin'.
 */
class UserAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || strlen($token) > 64) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $tokenRecord = UserToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        if (!$tokenRecord || !$tokenRecord->user) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $user = $tokenRecord->user;

        if (!$user->is_active) {
            return response()->json(['success' => false, 'error' => 'Account disabled'], 403);
        }

        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['success' => false, 'error' => 'Admin access required'], 403);
        }

        // Admin panel sessions expire 1 hour after the token was created,
        // regardless of the token's overall expiry (which may be 12h or 30d).
        if ($tokenRecord->created_at->lt(now()->subHour())) {
            return response()->json(['success' => false, 'error' => 'Admin session expired. Please sign in again.'], 401);
        }

        $request->merge([
            '_admin_user_id'   => $user->id,
            '_admin_user_role' => $user->role,
            '_admin_user'      => $user,
        ]);

        return $next($request);
    }
}
