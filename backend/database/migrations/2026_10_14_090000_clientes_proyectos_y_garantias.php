<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes públicos y privados (naturaleza + sector), proyectos de implementación
 * o de servicio con cliente final y orden de compra, y control de alertas de garantía.
 */
return new class extends Migration
{
    /** Tipos de entidad anteriores → sector equivalente. */
    private const SECTORES = [
        'Ministerio' => 'Gobierno nacional / Ministerios',
        'Gobierno Regional' => 'Gobierno regional',
        'Municipalidad Provincial' => 'Gobierno local / Municipalidades',
        'Municipalidad Distrital' => 'Gobierno local / Municipalidades',
        'Hospital / Salud' => 'Salud',
        'Universidad / Educación' => 'Educación',
        'Policía / Seguridad' => 'Seguridad / Policía',
    ];

    public function up(): void
    {
        Schema::table('entidades', function (Blueprint $table) {
            $table->string('naturaleza', 10)->default('publico')->after('ruc');
            $table->string('sector', 60)->nullable()->after('naturaleza');
            $table->string('correos_alertas')->nullable()->after('contacto_email');
        });

        foreach (DB::table('entidades')->select('id', 'tipo')->get() as $e) {
            DB::table('entidades')->where('id', $e->id)->update(['sector' => self::SECTORES[$e->tipo] ?? $e->tipo]);
        }

        Schema::table('entidades', fn (Blueprint $table) => $table->dropColumn('tipo'));

        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('clase', 15)->default('implementacion')->after('nombre');
            $table->foreignId('cliente_final_id')->nullable()->after('entidad_id')->constrained('entidades')->nullOnDelete();
            $table->string('orden_compra', 60)->nullable()->after('numero_contrato');
            $table->date('fecha_fin')->nullable()->after('fecha_entrega');
        });

        Schema::table('garantias', function (Blueprint $table) {
            $table->string('orden_compra', 60)->nullable()->after('numero_documento');
            $table->unsignedSmallInteger('duracion_meses')->nullable()->after('fecha_inicio');
            $table->text('observaciones')->nullable()->after('condiciones');
            $table->boolean('notificar_cliente')->default(true)->after('anulada');
        });

        // Cada aviso (60, 30, 15… días) se envía una sola vez por garantía.
        Schema::create('garantia_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('garantias')->cascadeOnDelete();
            $table->smallInteger('dias');
            $table->text('destinatarios')->nullable();
            $table->dateTime('enviada_en');

            $table->unique(['garantia_id', 'dias']);
        });

        DB::table('configuraciones')->where('clave', 'tipos_entidad')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('garantia_alertas');

        Schema::table('garantias', fn (Blueprint $table) => $table->dropColumn(['orden_compra', 'duracion_meses', 'observaciones', 'notificar_cliente']));

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_final_id');
            $table->dropColumn(['clase', 'orden_compra', 'fecha_fin']);
        });

        Schema::table('entidades', fn (Blueprint $table) => $table->string('tipo', 40)->default('Otro')->after('ruc'));
        DB::table('entidades')->update(['tipo' => DB::raw('COALESCE(sector, "Otro")')]);
        Schema::table('entidades', fn (Blueprint $table) => $table->dropColumn(['naturaleza', 'sector', 'correos_alertas']));
    }
};
