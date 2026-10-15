<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Expulsa a los usuarios desactivados por el Administrador aunque tengan sesión o token vigente. */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => 'Usuario desactivado.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Su usuario ha sido desactivado.']);
        }

        return $next($request);
    }
}
