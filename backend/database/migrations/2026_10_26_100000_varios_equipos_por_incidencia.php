<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una incidencia puede afectar a varios equipos instalados (p. ej. 3 de las 10 cámaras del proyecto):
 * - incidencias.equipo_id pasa a la tabla intermedia equipo_incidencia.
 * - Cada equipo de la hoja de servicio lleva su diagnóstico y su resultado, y sus fotos.
 * - Si en la visita unos equipos quedan reparados y otros pendientes, los pendientes pasan a una
 *   incidencia nueva que guarda de dónde viene (incidencia_origen_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incidencia_id', 'equipo_id']);
        });

        DB::table('incidencias')->whereNotNull('equipo_id')->orderBy('id')->each(fn ($i) => DB::table('equipo_incidencia')->insert([
            'incidencia_id' => $i->id, 'equipo_id' => $i->equipo_id, 'created_at' => $i->created_at, 'updated_at' => $i->created_at,
        ]));

        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipo_id');
            $table->foreignId('incidencia_origen_id')->nullable()->after('garantia_id')->constrained('incidencias')->nullOnDelete();
        });

        Schema::table('hoja_servicio_equipos', function (Blueprint $table) {
            $table->text('diagnostico')->nullable()->after('estado_final');
            $table->string('resultado', 20)->nullable()->after('diagnostico');
        });

        Schema::table('archivos', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->after('archivable_id')->constrained('equipos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('archivos', fn (Blueprint $table) => $table->dropConstrainedForeignId('equipo_id'));
        Schema::table('hoja_servicio_equipos', fn (Blueprint $table) => $table->dropColumn(['diagnostico', 'resultado']));

        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incidencia_origen_id');
            $table->foreignId('equipo_id')->nullable()->after('garantia_id')->constrained('equipos')->nullOnDelete();
        });

        // Al volver atrás se conserva el primer equipo de cada incidencia.
        DB::table('equipo_incidencia')->orderBy('id')->get()->groupBy('incidencia_id')
            ->each(fn ($filas, $id) => DB::table('incidencias')->where('id', $id)->update(['equipo_id' => $filas->first()->equipo_id]));

        Schema::dropIfExists('equipo_incidencia');
    }
};
