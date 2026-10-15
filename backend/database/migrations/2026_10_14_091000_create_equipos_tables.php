<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario de equipos de los clientes y su historial (hoja de vida):
 * instalación, mantenimientos, fallas, diagnósticos, cambios de componentes, entregas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable()->unique();
            $table->foreignId('entidad_id')->constrained('entidades')->restrictOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->string('categoria', 60);
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('serie', 80)->nullable()->index();
            $table->string('descripcion')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('area', 100)->nullable();
            $table->string('responsable')->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->string('estado', 20)->default('operativo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entidad_id', 'categoria']);
        });

        Schema::create('equipo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->dateTime('fecha');
            $table->string('titulo');
            $table->text('detalle')->nullable();
            $table->string('componente', 100)->nullable();
            $table->string('estado_equipo', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('incidencia_id')->nullable()->constrained('incidencias')->nullOnDelete();
            $table->foreignId('hoja_servicio_id')->nullable()->constrained('hojas_servicio')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['equipo_id', 'fecha']);
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->after('garantia_id')->constrained('equipos')->nullOnDelete();
            $table->string('cobertura', 20)->default('garantia')->after('equipo_id');
        });

        Schema::table('hoja_servicio_equipos', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->after('hoja_servicio_id')->constrained('equipos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hoja_servicio_equipos', fn (Blueprint $table) => $table->dropConstrainedForeignId('equipo_id'));
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipo_id');
            $table->dropColumn('cobertura');
        });
        Schema::dropIfExists('equipo_eventos');
        Schema::dropIfExists('equipos');
    }
};
