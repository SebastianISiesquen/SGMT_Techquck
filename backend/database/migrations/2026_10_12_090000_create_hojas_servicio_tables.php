<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hojas de servicio: documento firmado en campo por el técnico y el responsable de la entidad. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->nullable()->unique();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->foreignId('incidencia_id')->nullable()->constrained('incidencias')->nullOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_servicio', 20);
            $table->date('fecha');
            $table->time('hora_llegada');
            $table->time('hora_salida')->nullable();
            $table->text('trabajo_realizado');
            $table->text('observaciones')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->string('estado', 20)->default('pendiente_firma');
            $table->boolean('conforme')->default(false);
            $table->string('receptor_nombre')->nullable();
            $table->string('receptor_dni', 15)->nullable();
            $table->string('receptor_cargo')->nullable();
            $table->string('firma_receptor')->nullable();
            $table->string('firma_tecnico')->nullable();
            $table->dateTime('fecha_firma')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('origen', 10)->default('web');
            $table->string('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->index(['tecnico_id', 'fecha']);
            $table->index(['estado', 'fecha']);
        });

        Schema::create('hoja_servicio_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoja_servicio_id')->constrained('hojas_servicio')->cascadeOnDelete();
            $table->string('equipo');
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('serie', 80)->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('accion', 20);
            $table->string('estado_final', 20);
            $table->string('observacion')->nullable();
        });

        Schema::create('hoja_servicio_materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoja_servicio_id')->constrained('hojas_servicio')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('unidad', 20)->default('und');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoja_servicio_materiales');
        Schema::dropIfExists('hoja_servicio_equipos');
        Schema::dropIfExists('hojas_servicio');
    }
};
