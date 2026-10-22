<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * - Sin firmas digitales: la conformidad es el acta firmada que se sube (foto o PDF).
 *   Estados de la hoja: registrada (pendiente de acta) → conforme (con acta) | anulada.
 * - Visitas de varios días: cada hoja indica lo pendiente y la fecha de la próxima visita.
 * - Hoja de servicio más completa: verificaciones previas de seguridad y mediciones.
 * - QR público de los equipos con un enlace no adivinable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hojas_servicio', function (Blueprint $table) {
            $table->json('verificaciones')->nullable()->after('hora_salida');
            $table->json('mediciones')->nullable()->after('recomendaciones');
            $table->text('pendientes')->nullable()->after('resultado');
            $table->date('proxima_visita')->nullable()->after('pendientes');
        });

        DB::table('hojas_servicio')->where('estado', 'pendiente_firma')->update(['estado' => 'registrada']);
        DB::table('hojas_servicio')->where('estado', 'firmada')->update(['estado' => 'conforme']);

        Schema::table('incidencias', function (Blueprint $table) {
            $table->date('proxima_visita')->nullable()->after('fecha_limite');
            $table->string('motivo_reprogramacion')->nullable()->after('proxima_visita');
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->string('token_publico', 32)->nullable()->unique()->after('codigo');
        });
        foreach (DB::table('equipos')->whereNull('token_publico')->pluck('id') as $id) {
            DB::table('equipos')->where('id', $id)->update(['token_publico' => Str::lower(Str::random(20))]);
        }
    }

    public function down(): void
    {
        Schema::table('equipos', fn (Blueprint $t) => $t->dropColumn('token_publico'));
        Schema::table('incidencias', fn (Blueprint $t) => $t->dropColumn(['proxima_visita', 'motivo_reprogramacion']));
        DB::table('hojas_servicio')->where('estado', 'registrada')->update(['estado' => 'pendiente_firma']);
        DB::table('hojas_servicio')->where('estado', 'conforme')->update(['estado' => 'firmada']);
        Schema::table('hojas_servicio', fn (Blueprint $t) => $t->dropColumn(['verificaciones', 'mediciones', 'pendientes', 'proxima_visita']));
    }
};
