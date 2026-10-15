<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/** Matriz de módulos por rol: el Administrador define qué ve el Supervisor y el Técnico. */
class RolController extends Controller
{
    public function index()
    {
        return view('admin.roles', [
            'roles' => Role::with('permissions')->withCount('users')->get(),
            'grupos' => config('sggp.permisos'),
        ]);
    }

    public function update(Request $request, Role $rol)
    {
        if ($rol->name === 'Administrador') {
            return back()->withErrors(['general' => 'El rol Administrador siempre tiene acceso total.']);
        }

        $request->validate(['permisos' => ['nullable', 'array'], 'permisos.*' => ['exists:permissions,name']]);

        $rol->syncPermissions($request->input('permisos', []));
        Auditor::log('permisos', 'usuarios', "Actualizó los permisos del rol {$rol->name}", null, ['permisos' => $request->input('permisos', [])]);

        return back()->with('ok', "Permisos del rol {$rol->name} actualizados.");
    }
}
