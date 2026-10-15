<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $datos['email'])->first();

        if (! $user || ! Hash::check($datos['password'], $user->password) || ! $user->activo) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->can('atenciones.registrar')) {
            throw ValidationException::withMessages(['email' => 'La app móvil es para personal técnico. Use la plataforma web.']);
        }

        $user->update(['ultimo_acceso' => now()]);
        $token = $user->createToken($datos['dispositivo'] ?? 'app-movil', ['tecnico'], now()->addDays(30))->plainTextToken;

        auth()->setUser($user);
        Auditor::log('login', 'acceso', 'Inicio de sesión (app móvil)');

        return response()->json(['token' => $token, 'usuario' => $this->usuario($user)]);
    }

    public function me(Request $request)
    {
        return ['usuario' => $this->usuario($request->user())];
    }

    public function logout(Request $request)
    {
        Auditor::log('logout', 'acceso', 'Cierre de sesión (app móvil)');
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function notificaciones(Request $request)
    {
        return $request->user()->notifications()->limit(30)->get()->map(fn ($n) => [
            'id' => $n->id,
            'titulo' => $n->data['titulo'] ?? '',
            'mensaje' => $n->data['mensaje'] ?? '',
            'incidencia_id' => $n->data['incidencia_id'] ?? null,
            'leida' => (bool) $n->read_at,
            'fecha' => $n->created_at->toIso8601String(),
        ]);
    }

    public function leerNotificaciones(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    private function usuario(User $user): array
    {
        return [
            'id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'cargo' => $user->cargo,
            'telefono' => $user->telefono,
            'rol' => $user->rolNombre(),
            'iniciales' => $user->iniciales(),
            'no_leidas' => $user->unreadNotifications()->count(),
        ];
    }
}
