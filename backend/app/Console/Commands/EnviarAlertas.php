<?php

namespace App\Console\Commands;

use App\Enums\EstadoIncidencia;
use App\Models\AlmacenArticulo;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\IncidenciaSinAtender;
use App\Notifications\MantenimientoProximo;
use App\Notifications\StockBajo;
use App\Services\AlertaGarantiaService;
use App\Services\Auditor;
use App\Support\Ajustes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Se ejecuta a diario (ver routes/console.php):
 * - avisos de vencimiento de garantías al supervisor y al cliente (60, 30, 15, 7 y 0 días);
 * - mantenimientos programados que se acercan;
 * - incidencias sin atender;
 * - artículos del almacén bajo el stock mínimo;
 * - servicios cuyo contrato terminó pasan a «Finalizado».
 */
class EnviarAlertas extends Command
{
    protected $signature = 'sggp:alertas';

    protected $description = 'Genera alertas de garantías por vencer, mantenimientos próximos e incidencias sin atender';

    public function handle(AlertaGarantiaService $garantias): int
    {
        $enviadas = $garantias->procesar();
        $enviadas += $this->mantenimientos();
        $enviadas += $this->incidenciasSinAtender();
        $enviadas += $this->stockBajo();
        $finalizados = $this->finalizarServiciosVencidos();

        $this->info("Alertas generadas: $enviadas · Servicios finalizados: $finalizados");

        return self::SUCCESS;
    }

    private function mantenimientos(): int
    {
        $supervisores = User::activos()->permission('mantenimientos.gestionar')->get()
            ->merge(User::role('Administrador')->activos()->get())
            ->unique('id');
        $enviadas = 0;

        Mantenimiento::with(['plan.proyecto.entidad', 'plan.proyecto.clienteFinal', 'tecnico'])
            ->pendientes()
            ->where('alerta_enviada', false)
            ->whereDate('fecha_programada', '<=', today()->addDays(Ajustes::int('dias_alerta_mantenimiento')))
            ->each(function (Mantenimiento $m) use ($supervisores, &$enviadas) {
                $destinatarios = $supervisores->when($m->tecnico?->activo, fn ($c) => $c->push($m->tecnico))->unique('id');
                Notification::send($destinatarios, new MantenimientoProximo($m));
                $m->update(['alerta_enviada' => true]);
                $enviadas++;
            });

        return $enviadas;
    }

    private function incidenciasSinAtender(): int
    {
        $dias = Ajustes::int('dias_incidencia_sin_atender');
        $supervisores = User::activos()->permission('incidencias.asignar')->get()
            ->merge(User::role('Administrador')->activos()->get())
            ->unique('id');
        $enviadas = 0;

        Incidencia::with('tecnico')
            ->whereIn('estado', [EstadoIncidencia::Registrada, EstadoIncidencia::Asignada])
            ->where('fecha_reporte', '<=', now()->subDays($dias))
            ->each(function (Incidencia $i) use ($supervisores, &$enviadas) {
                if (Cache::add("alerta-incidencia-{$i->id}-".today()->toDateString(), 1, now()->addDay())) {
                    Notification::send($supervisores, new IncidenciaSinAtender($i, (int) $i->fecha_reporte->diffInDays(now())));
                    $enviadas++;
                }
            });

        return $enviadas;
    }

    private function stockBajo(): int
    {
        $articulos = AlmacenArticulo::where('activo', true)->bajoMinimo()->orderBy('nombre')->get();
        if ($articulos->isEmpty() || ! Cache::add('alerta-stock-'.today()->toDateString(), 1, now()->addDay())) {
            return 0;
        }

        $responsables = User::activos()->permission('almacen.gestionar')->get()
            ->merge(User::role('Administrador')->activos()->get())->unique('id');
        Notification::send($responsables, new StockBajo($articulos));

        return 1;
    }

    private function finalizarServiciosVencidos(): int
    {
        $vencidos = Proyecto::where('clase', 'servicio')->where('estado', 'operativo')
            ->whereNotNull('fecha_fin')->whereDate('fecha_fin', '<', today())->get();

        foreach ($vencidos as $p) {
            $p->update(['estado' => 'finalizado']);
            Auditor::log('editar', 'proyectos', "El servicio {$p->codigo} terminó su contrato el {$p->fecha_fin->format('d/m/Y')} y pasó a Finalizado", $p);
        }

        return $vencidos->count();
    }
}
