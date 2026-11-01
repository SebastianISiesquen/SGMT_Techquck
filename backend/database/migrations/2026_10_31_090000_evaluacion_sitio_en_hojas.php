<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluación preliminar del sitio en la hoja de servicio: ambiente, aire acondicionado,
 * material inflamable y acceso, cada uno con la observación del técnico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hojas_servicio', function (Blueprint $table) {
            $table->json('evaluacion')->nullable()->after('verificaciones');
        });
    }

    public function down(): void
    {
        Schema::table('hojas_servicio', fn (Blueprint $table) => $table->dropColumn('evaluacion'));
    }
};
