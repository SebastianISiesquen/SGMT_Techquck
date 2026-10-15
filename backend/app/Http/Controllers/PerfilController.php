<?php

namespace App\Http\Controllers;

use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        return view('perfil', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'telefono' => \App\Support\Reglas::telefono(),
        ]);

        $user->update($datos);
        Auditor::log('editar', 'usuarios', 'Actualizó su perfil', $user);

        return back()->with('ok', 'Perfil actualizado.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $request->password]);
        Auditor::log('password', 'usuarios', 'Cambió su contraseña', $request->user());

        return back()->with('ok', 'Contraseña actualizada.');
    }
}
