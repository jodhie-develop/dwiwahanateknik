<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Jika akun dinonaktifkan saat masih login, sesi langsung diakhiri. */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Akun Anda tidak aktif. Hubungi admin.']);
        }

        return $next($request);
    }
}
