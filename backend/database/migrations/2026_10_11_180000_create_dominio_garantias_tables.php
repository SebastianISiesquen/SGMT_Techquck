<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo relacional del dominio:
 * Entidad → Proyecto → Garantía → Incidencia → Atención → Archivo (evidencia)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('tipo', 40);
            $table->string('direccion')->nullable();
            $table->string('departamento', 60)->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_cargo')->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->string('contacto_email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->nullable()->unique();
            $table->string('nombre');
            $table->foreignId('entidad_id')->constrained('entidades')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->text('descripcion')->nullable();
            $table->string('numero_contrato', 60)->nullable();
            $table->decimal('monto_contrato', 14, 2)->nullable();
            $table->string('ubicacion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_entrega');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('operativo');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entidad_id', 'estado']);
        });

        Schema::create('garantias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->nullable()->unique();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('descripcion');
            $table->text('cobertura')->nullable();
            $table->text('condiciones')->nullable();
            $table->string('proveedor')->nullable();
            $table->string('numero_documento', 60)->nullable();
            $table->decimal('monto', 14, 2)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_vencimiento');
            $table->boolean('anulada')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha_vencimiento');
        });

        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->nullable()->unique();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('prioridad', 15)->default('media');
            $table->string('estado', 20)->default('registrada');
            $table->string('canal', 20)->nullable();
            $table->string('reportado_por')->nullable();
            $table->string('ubicacion_detalle')->nullable();
            $table->dateTime('fecha_reporte');
            $table->dateTime('fecha_limite')->nullable();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_asignacion')->nullable();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'prioridad']);
            $table->index(['tecnico_id', 'estado']);
        });

        Schema::create('atenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->text('diagnostico');
            $table->text('actividades');
            $table->text('observaciones')->nullable();
            $table->string('resultado', 30);
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('origen', 10)->default('web');
            $table->timestamps();
        });

        // Documentos y evidencias de cualquier registro (proyecto, garantía, incidencia, atención)
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->morphs('archivable');
            $table->string('categoria', 30);
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime', 120);
            $table->unsignedBigInteger('tamano');
            $table->string('descripcion')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Trazabilidad del ciclo de atención de cada incidencia
        Schema::create('incidencia_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30);
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20)->nullable();
            $table->text('detalle')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Auditoría general de acciones de usuarios
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 40);
            $table->string('modulo', 40);
            $table->nullableMorphs('auditable');
            $table->string('descripcion');
            $table->json('datos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modulo', 'created_at']);
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('incidencia_historial');
        Schema::dropIfExists('archivos');
        Schema::dropIfExists('atenciones');
        Schema::dropIfExists('incidencias');
        Schema::dropIfExists('garantias');
        Schema::dropIfExists('proyectos');
        Schema::dropIfExists('entidades');
    }
};
