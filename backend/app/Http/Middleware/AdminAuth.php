<?php

namespace App\Http\Middleware;

use App\Models\AdminToken;
use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (!$bearer || strlen($bearer) > 64) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $tokenRecord = AdminToken::where('token', $bearer)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        // Attach user to request for controllers to use
        $request->merge(['_admin_user_id' => $tokenRecord->user_id]);

        return $next($request);
    }
}
