<?php

namespace App\Http\Controllers;

use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([...$credenciales, 'activo' => true], $request->boolean('remember'))) {
            Auditor::log('login_fallido', 'acceso', "Intento de acceso fallido: {$credenciales['email']}");

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();
        $request->user()->update(['ultimo_acceso' => now()]);
        Auditor::log('login', 'acceso', 'Inicio de sesión (web)');

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request)
    {
        Auditor::log('logout', 'acceso', 'Cierre de sesión (web)');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
