<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurityMiddleware
{
    /**
     * Handle an incoming request and enforce enterprise session security policies:
     * 1. IP Binding (immediate session invalidation on public IP change)
     * 2. Absolute Session Timeout (maximum 8 hours duration)
     * 3. Idle Inactivity Timeout (15 minutes threshold, excluding passive syncs)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->timestamp;
        $currentIp = $request->ip();
        $user = Auth::user();

        // 1. Pengikatan IP (IP Binding)
        // Invalidate session immediately if client IP changes to prevent session hijacking
        if (!$session->has('session_bound_ip')) {
            $session->put('session_bound_ip', $currentIp);
        } else {
            $boundIp = $session->get('session_bound_ip');
            if ($boundIp && $currentIp !== $boundIp) {
                Log::warning("SessionSecurity: IP binding mismatch for account {$user->email}. Bound IP: {$boundIp}, Current IP: {$currentIp}. Destroying session.");

                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return $this->terminateSessionResponse(
                    $request,
                    'ip_binding',
                    'Sesi Anda ditutup demi keamanan karena perubahan alamat IP publik terdeteksi. Silakan login kembali.'
                );
            }
        }

        // 2. Batas Maksimal Sesi (Absolute Timeout - Default: 8 Jam)
        $maxAbsolute = (int) config('session.max_absolute_lifetime', 8 * 3600);
        if (!$session->has('session_login_time')) {
            $session->put('session_login_time', $now);
        } else {
            $loginTime = (int) $session->get('session_login_time');
            if (($now - $loginTime) > $maxAbsolute) {
                Log::info("SessionSecurity: Absolute timeout (8h) reached for account {$user->email}.");

                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return $this->terminateSessionResponse(
                    $request,
                    'absolute_timeout',
                    'Batas maksimal durasi sesi (8 jam) telah tercapai. Demi kepatuhan keamanan data, silakan login kembali.'
                );
            }
        }

        // 3. Batas Tanpa Aktivitas (Idle Timeout - Default: 15 Menit)
        $idleTimeout = (int) config('session.idle_timeout', 15 * 60);
        $isPassiveSync = $request->routeIs('webmail.check-updates') || $request->hasHeader('X-Passive-Sync');

        if (!$session->has('session_last_activity')) {
            $session->put('session_last_activity', $now);
        } else {
            $lastActivity = (int) $session->get('session_last_activity');
            if (($now - $lastActivity) > $idleTimeout) {
                Log::info("SessionSecurity: Idle timeout (15m) reached for account {$user->email}.");

                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return $this->terminateSessionResponse(
                    $request,
                    'idle_timeout',
                    'Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit. Silakan login kembali.'
                );
            }

            // Only update last activity timestamp for active user actions (not passive background syncs)
            if (!$isPassiveSync) {
                $session->put('session_last_activity', $now);
            }
        }

        return $next($request);
    }

    /**
     * Return appropriate redirect or JSON response when session is terminated.
     */
    protected function terminateSessionResponse(Request $request, string $reason, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'session_expired' => true,
                'reason' => $reason,
                'message' => $message,
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
