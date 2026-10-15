<?php

namespace App\Console\Commands;

use App\Models\Proyecto;
use App\Services\InformeClienteService;
use Illuminate\Console\Command;

/** El día 1 de cada mes envía el informe del mes anterior a los clientes que lo tienen activado (ver routes/console.php). */
class EnviarInformes extends Command
{
    protected $signature = 'sggp:informes {--mes= : Mes a informar (AAAA-MM); por defecto el mes anterior}';

    protected $description = 'Envía el informe mensual a los clientes con proyectos o servicios que lo tienen activado';

    public function handle(InformeClienteService $informes): int
    {
        $desde = $this->option('mes') ? now()->createFromFormat('Y-m-d', $this->option('mes').'-01')->startOfDay() : now()->subMonthNoOverflow()->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        $enviados = 0;

        Proyecto::with('entidad')->where('informe_mensual', true)->where('estado', 'operativo')->each(function (Proyecto $p) use ($informes, $desde, $hasta, &$enviados) {
            if ($informes->enviar($p, $desde, $hasta)) {
                $enviados++;
            }
        });

        $this->info("Informes enviados: $enviados ({$desde->format('m/Y')})");

        return self::SUCCESS;
    }
}
