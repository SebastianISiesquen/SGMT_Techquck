<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador que genera la app al abrir la hoja: si el envío llegó pero se cortó la señal
 * antes de recibir la respuesta, el reintento devuelve la misma hoja en vez de duplicarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hojas_servicio', function (Blueprint $table) {
            $table->string('uuid_cliente', 40)->nullable()->unique()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('hojas_servicio', fn (Blueprint $table) => $table->dropColumn('uuid_cliente'));
    }
};
