<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retira el módulo de Sedes en bases de datos que llegaron a tenerlo.
 * En una instalación nueva no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hojas_servicio', 'incidencias'] as $tabla) {
            if (Schema::hasColumn($tabla, 'sede_id')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropConstrainedForeignId('sede_id'));
            }
        }

        Schema::dropIfExists('proyecto_sede');
        Schema::dropIfExists('sedes');

        // Las asignaciones a roles y usuarios se eliminan en cascada.
        DB::table('permissions')->whereIn('name', ['sedes.ver', 'sedes.gestionar'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('configuraciones')->where('clave', 'tipos_sede')->delete();
    }

    public function down(): void
    {
        // El módulo de Sedes se retiró del sistema; no se restaura.
    }
};
