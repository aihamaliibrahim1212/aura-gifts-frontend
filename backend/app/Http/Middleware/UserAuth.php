<?php

namespace App\Http\Middleware;

use App\Models\UserToken;
use Closure;
use Illuminate\Http\Request;

class UserAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);

        if (!$token || strlen($token) > 64) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $tokenRecord = UserToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $user = $tokenRecord->user;
        if (!$user || !$user->is_active) {
            return response()->json(['success' => false, 'error' => 'Account disabled'], 403);
        }

        $request->merge(['_user_id' => $tokenRecord->user_id]);
        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        // Prefer Authorization: Bearer header
        $bearer = $request->bearerToken();
        if ($bearer) return $bearer;

        // Fallback: HTTP-only cookie named 'user_token'
        return $request->cookie('user_token');
    }
}
