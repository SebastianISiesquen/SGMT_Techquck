<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - La hoja de servicio pasa a ser el registro único de cada visita: lleva diagnóstico y resultado,
 *   y crea la atención técnica de la incidencia.
 * - Almacén de consumibles y repuestos, con kardex de movimientos (la hoja descuenta lo que se usó).
 * - Checklist de mantenimiento por categoría de equipo.
 * - Informe mensual automático al cliente por proyecto o servicio.
 * - Las garantías quedan solo para equipos y trabajos (el soporte es un contrato de servicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hojas_servicio', 'diagnostico')) Schema::table('hojas_servicio', function (Blueprint $table) {
            $table->text('diagnostico')->nullable()->after('hora_salida');
            $table->string('resultado', 30)->nullable()->after('recomendaciones');
            $table->foreignId('atencion_id')->nullable()->after('mantenimiento_id')->constrained('atenciones')->nullOnDelete();
        });

        if (! Schema::hasColumn('hoja_servicio_equipos', 'checklist')) Schema::table('hoja_servicio_equipos', function (Blueprint $table) {
            $table->json('checklist')->nullable();
        });

        if (! Schema::hasColumn('proyectos', 'informe_mensual')) Schema::table('proyectos', function (Blueprint $table) {
            $table->boolean('informe_mensual')->default(false)->after('estado');
        });

        if (! Schema::hasTable('almacen_articulos')) Schema::create('almacen_articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable()->unique();
            $table->string('tipo', 15);                       // consumible | repuesto
            $table->string('nombre');
            $table->string('categoria', 60)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('unidad', 20)->default('und');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('ubicacion', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        if (! Schema::hasTable('almacen_movimientos')) Schema::create('almacen_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('almacen_articulos')->cascadeOnDelete();
            $table->string('tipo', 10);                       // entrada | salida | ajuste
            $table->decimal('cantidad', 12, 2);               // positiva; el tipo indica el sentido (ajuste puede ser negativo)
            $table->decimal('stock_resultante', 12, 2);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('motivo')->nullable();
            $table->string('documento', 60)->nullable();      // factura, guía, OC
            $table->foreignId('hoja_servicio_id')->nullable()->constrained('hojas_servicio')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha')->useCurrent();

            $table->index(['articulo_id', 'fecha']);
        });

        if (! Schema::hasColumn('hoja_servicio_materiales', 'articulo_id')) Schema::table('hoja_servicio_materiales', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('hoja_servicio_id')->constrained('almacen_articulos')->nullOnDelete();
        });

        if (! Schema::hasTable('checklists')) Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 60)->unique();
            $table->text('items');                            // un punto de control por línea
            $table->timestamps();
        });

        // Garantías: el «soporte técnico y mantenimiento» es un contrato de servicio, no una garantía.
        $servicios = DB::table('proyectos')->where('clase', 'servicio')->pluck('id');
        DB::table('garantias')->where('tipo', 'Soporte técnico y mantenimiento')->whereIn('proyecto_id', $servicios)
            ->update(['tipo' => 'Garantía del servicio']);
        DB::table('garantias')->where('tipo', 'Soporte técnico y mantenimiento')
            ->update(['tipo' => 'Garantía de instalación']);

        $catalogo = DB::table('configuraciones')->where('clave', 'tipos_garantia')->value('valor');
        if ($catalogo !== null) {
            $lineas = collect(preg_split('/\r\n|\r|\n/', $catalogo))->map(fn ($l) => trim($l))->filter()
                ->map(fn ($l) => $l === 'Soporte técnico y mantenimiento' ? 'Garantía del servicio' : $l)->unique()->values();
            DB::table('configuraciones')->where('clave', 'tipos_garantia')->update(['valor' => $lineas->implode("\n")]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
        Schema::table('hoja_servicio_materiales', fn (Blueprint $t) => $t->dropConstrainedForeignId('articulo_id'));
        Schema::dropIfExists('almacen_movimientos');
        Schema::dropIfExists('almacen_articulos');
        Schema::table('proyectos', fn (Blueprint $t) => $t->dropColumn('informe_mensual'));
        Schema::table('hoja_servicio_equipos', fn (Blueprint $t) => $t->dropColumn('checklist'));
        Schema::table('hojas_servicio', function (Blueprint $t) {
            $t->dropConstrainedForeignId('atencion_id');
            $t->dropColumn(['diagnostico', 'resultado']);
        });
    }
};
