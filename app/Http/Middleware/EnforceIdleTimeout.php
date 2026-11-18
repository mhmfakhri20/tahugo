<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AUTH-6: log the user out after a period without any request. The limit depends on the role
 * (config/tahugo.php → idle_timeout).
 */
class EnforceIdleTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $minutes = (int) config('tahugo.idle_timeout.'.$user->role->value, 60);
        $last = $request->session()->get(self::SESSION_KEY);
        $now = now()->getTimestamp();

        if ($last !== null && $minutes > 0 && ($now - (int) $last) > $minutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk lagi.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->with('status', $message);
        }

        $request->session()->put(self::SESSION_KEY, $now);

        return $next($request);
    }
}
