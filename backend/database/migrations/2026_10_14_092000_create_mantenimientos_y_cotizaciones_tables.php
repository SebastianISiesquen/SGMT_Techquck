<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes de mantenimiento preventivo (p. ej. 4 visitas al año) con sus visitas programadas,
 * y cotizaciones para servicios sin cobertura (diagnóstico a pedido del cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('frecuencia_meses');
            $table->unsignedSmallInteger('total_visitas');
            $table->date('fecha_inicio');
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alcance')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_mantenimiento')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_programada');
            $table->string('estado', 15)->default('programado');
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hoja_servicio_id')->nullable()->constrained('hojas_servicio')->nullOnDelete();
            $table->date('fecha_ejecucion')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('alerta_enviada')->default(false);
            $table->timestamps();

            $table->index(['estado', 'fecha_programada']);
        });

        Schema::table('hojas_servicio', function (Blueprint $table) {
            $table->foreignId('mantenimiento_id')->nullable()->after('incidencia_id')->constrained('mantenimientos')->nullOnDelete();
        });

        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->nullable()->unique();
            $table->foreignId('entidad_id')->constrained('entidades')->restrictOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('incidencia_id')->nullable()->constrained('incidencias')->nullOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->date('fecha');
            $table->unsignedSmallInteger('validez_dias')->default(15);
            $table->string('moneda', 3)->default('PEN');
            $table->string('asunto');
            $table->text('diagnostico')->nullable();
            $table->text('condiciones')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado', 15)->default('borrador');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('unidad', 20)->default('und');
            $table->decimal('precio_unitario', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');
        Schema::table('hojas_servicio', fn (Blueprint $table) => $table->dropConstrainedForeignId('mantenimiento_id'));
        Schema::dropIfExists('mantenimientos');
        Schema::dropIfExists('planes_mantenimiento');
    }
};
