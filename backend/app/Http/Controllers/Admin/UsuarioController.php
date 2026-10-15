<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/** Gestión de usuarios, rol y permisos adicionales por usuario (RF02). */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::with('roles')
            ->when($request->buscar, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($request->rol, fn ($q, $v) => $q->role($v))
            ->orderBy('name')
            ->paginate(15)->withQueryString();

        return view('admin.usuarios.index', ['usuarios' => $usuarios, 'roles' => Role::pluck('name')]);
    }

    public function create()
    {
        return view('admin.usuarios.form', $this->datosFormulario(new User(['activo' => true])));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $usuario = User::create($datos);
        $this->sincronizarAcceso($request, $usuario);

        Auditor::log('crear', 'usuarios', "Creó el usuario {$usuario->email} con rol {$request->rol}", $usuario);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.form', $this->datosFormulario($usuario));
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $this->validar($request, $usuario);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        if ($usuario->is($request->user()) && $request->rol !== 'Administrador' && $usuario->esAdministrador()) {
            return back()->withErrors(['rol' => 'No puede quitarse a sí mismo el rol de Administrador.']);
        }

        $usuario->update($datos);
        $this->sincronizarAcceso($request, $usuario);

        Auditor::log('editar', 'usuarios', "Editó el usuario {$usuario->email}", $usuario, [
            'rol' => $request->rol,
            'permisos_extra' => $request->input('permisos', []),
        ]);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function toggle(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors(['general' => 'No puede desactivar su propio usuario.']);
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        if (! $usuario->activo) {
            $usuario->tokens()->delete(); // cierra sesión en la app móvil
        }

        Auditor::log($usuario->activo ? 'activar' : 'desactivar', 'usuarios', ($usuario->activo ? 'Activó' : 'Desactivó')." al usuario {$usuario->email}", $usuario);

        return back()->with('ok', $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    private function datosFormulario(User $usuario): array
    {
        return [
            'usuario' => $usuario,
            'roles' => Role::with('permissions')->get(),
            'grupos' => config('sggp.permisos'),
            'permisosDirectos' => $usuario->exists ? $usuario->getDirectPermissions()->pluck('name')->all() : [],
        ];
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($usuario?->id)],
            'dni' => \App\Support\Reglas::dni(),
            'telefono' => \App\Support\Reglas::telefono(),
            'cargo' => ['nullable', 'string', 'max:100'],
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'rol' => ['required', 'exists:roles,name'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['exists:permissions,name'],
        ]);

        return collect($datos)->except(['rol', 'permisos'])->all();
    }

    /** Rol principal + módulos adicionales asignados por el Administrador. */
    private function sincronizarAcceso(Request $request, User $usuario): void
    {
        $usuario->syncRoles([$request->rol]);

        $delRol = Role::findByName($request->rol)->permissions->pluck('name');
        $extra = collect($request->input('permisos', []))->diff($delRol)->values();
        $usuario->syncPermissions($extra);
    }
}
