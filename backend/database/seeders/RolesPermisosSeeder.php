<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $todos = collect(config('sggp.permisos'))->flatMap(fn ($grupo) => array_keys($grupo));

        $nuevos = $todos->filter(fn ($p) => Permission::findOrCreate($p, 'web')->wasRecentlyCreated);

        foreach (config('sggp.roles') as $rol => $permisos) {
            $role = Role::findOrCreate($rol, 'web');
            $porDefecto = collect($permisos === '*' ? $todos : $permisos);

            // Rol nuevo: todos sus permisos por defecto. Rol existente: solo los módulos
            // recién agregados al sistema, para no pisar lo que el Administrador configuró.
            $role->givePermissionTo($role->wasRecentlyCreated ? $porDefecto : $porDefecto->intersect($nuevos));
        }
    }
}
