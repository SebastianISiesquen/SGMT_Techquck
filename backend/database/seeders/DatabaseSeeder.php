<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesPermisosSeeder::class, ChecklistsSeeder::class]);

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@techquk.com.pe')],
            [
                'name' => 'Administrador del Sistema',
                'password' => env('ADMIN_PASSWORD', 'Admin2026*'),
                'cargo' => 'Administrador',
                'activo' => true,
            ],
        );
        $admin->syncRoles('Administrador');

        // Datos de demostración solo fuera de producción (o con --class=DemoSeeder).
        if (! app()->environment('production')) {
            $this->call([DemoSeeder::class, DemoEquiposSeeder::class, DemoHojasSeeder::class, DemoAlmacenSeeder::class]);
        }
    }
}
