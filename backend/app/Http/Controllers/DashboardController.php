<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Panel de control para la supervisión (RF12). */
class DashboardController extends Controller
{
    /** Cada rol aterriza en su pantalla principal. */
    public function inicio(Request $request)
    {
        $user = $request->user();

        return match (true) {
            $user->can('dashboard.ver') => redirect()->route('dashboard'),
            $user->can('atenciones.registrar') => redirect()->route('mis-incidencias.index'),
            default => redirect()->route('perfil'),
        };
    }

    public function index()
    {
        $garantias = [
            'vigente' => Garantia::vigentes()->count(),
            'por_vencer' => Garantia::porVencer()->count(),
            'vencida' => Garantia::vencidas()->count(),
        ];

        $porEstado = Incidencia::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')->pluck('total', 'estado');

        $kpis = [
            'entidades' => Entidad::where('activo', true)->count(),
            'proyectos' => Proyecto::where('estado', 'operativo')->count(),
            'incidencias_abiertas' => Incidencia::abiertas()->count(),
            'por_cerrar' => (int) ($porEstado[EstadoIncidencia::Resuelta->value] ?? 0),
            'sla_vencido' => Incidencia::abiertas()->whereNotNull('fecha_limite')->where('fecha_limite', '<', now())->count(),
            'horas_promedio' => $this->horasPromedioResolucion(),
        ];

        // Incidencias por estado
        $estados = collect(EstadoIncidencia::cases())->reject(fn ($e) => $e === EstadoIncidencia::Anulada);
        $chartEstados = [
            'labels' => $estados->map->label()->values(),
            'data' => $estados->map(fn ($e) => (int) ($porEstado[$e->value] ?? 0))->values(),
        ];

        // Reportadas vs cerradas, últimos 12 meses
        $meses = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $desde = $meses->first();
        $reportadas = Incidencia::where('fecha_reporte', '>=', $desde)->get(['fecha_reporte'])
            ->countBy(fn ($i) => $i->fecha_reporte->format('Y-m'));
        $cerradas = Incidencia::where('fecha_cierre', '>=', $desde)->where('estado', EstadoIncidencia::Cerrada)->get(['fecha_cierre'])
            ->countBy(fn ($i) => $i->fecha_cierre->format('Y-m'));
        $chartTendencia = [
            'labels' => $meses->map(fn (Carbon $m) => ucfirst($m->translatedFormat('M y'))),
            'reportadas' => $meses->map(fn ($m) => $reportadas[$m->format('Y-m')] ?? 0),
            'cerradas' => $meses->map(fn ($m) => $cerradas[$m->format('Y-m')] ?? 0),
        ];

        // Vencimientos de garantías en los próximos 12 meses
        $proximos = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->addMonths($i));
        $vencen = Garantia::where('anulada', false)
            ->whereBetween('fecha_vencimiento', [today(), $proximos->last()->copy()->endOfMonth()])
            ->get(['fecha_vencimiento'])
            ->countBy(fn ($g) => $g->fecha_vencimiento->format('Y-m'));
        $chartVencimientos = [
            'labels' => $proximos->map(fn (Carbon $m) => ucfirst($m->translatedFormat('M y'))),
            'data' => $proximos->map(fn ($m) => $vencen[$m->format('Y-m')] ?? 0),
        ];

        // Incidencias abiertas por entidad (top 6)
        $porEntidad = Incidencia::abiertas()
            ->join('proyectos', 'proyectos.id', '=', 'incidencias.proyecto_id')
            ->join('entidades', 'entidades.id', '=', 'proyectos.entidad_id')
            ->select('entidades.nombre', DB::raw('count(*) as total'))
            ->groupBy('entidades.nombre')->orderByDesc('total')->limit(6)->get();
        $chartEntidades = [
            'labels' => $porEntidad->pluck('nombre'),
            'data' => $porEntidad->pluck('total'),
        ];

        $proximasVencer = Garantia::porVencer()->with('proyecto.entidad')->orderBy('fecha_vencimiento')->limit(6)->get();
        $incidenciasCriticas = Incidencia::abiertas()->with(['proyecto.entidad', 'tecnico'])
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->orderBy('fecha_reporte')->limit(6)->get();

        // Equipos, mantenimientos y cotizaciones
        $operacion = [
            'equipos' => Equipo::where('estado', '!=', 'de_baja')->count(),
            'equipos_falla' => Equipo::whereIn('estado', ['con_falla', 'en_reparacion', 'inoperativo'])->count(),
            'mant_atrasados' => Mantenimiento::pendientes()->whereDate('fecha_programada', '<', today())->count(),
            'mant_mes' => Mantenimiento::pendientes()->whereBetween('fecha_programada', [today(), today()->addDays(30)])->count(),
            'cotizaciones_enviadas' => Cotizacion::where('estado', 'enviada')->count(),
        ];
        $proximosMantenimientos = Mantenimiento::pendientes()->with(['plan.proyecto.entidad', 'plan.proyecto.clienteFinal', 'tecnico'])
            ->whereDate('fecha_programada', '<=', today()->addDays(30))
            ->orderBy('fecha_programada')->limit(6)->get();
        // Equipos con fallas repetidas en los últimos 6 meses: candidatos a revisión de fondo o reemplazo.
        $fallasRecurrentes = Equipo::with('entidad')
            ->withCount(['eventos as fallas' => fn ($q) => $q->where('tipo', 'falla')->where('fecha', '>=', now()->subMonths(6))])
            ->having('fallas', '>=', 2)->orderByDesc('fallas')->limit(5)->get();

        return view('dashboard', compact(
            'garantias', 'kpis', 'chartEstados', 'chartTendencia', 'chartVencimientos', 'chartEntidades',
            'proximasVencer', 'incidenciasCriticas', 'operacion', 'proximosMantenimientos', 'fallasRecurrentes',
        ));
    }

    private function horasPromedioResolucion(): ?float
    {
        $promedio = Incidencia::whereNotNull('fecha_resolucion')
            ->where('fecha_reporte', '>=', now()->subYear())
            ->get(['fecha_reporte', 'fecha_resolucion'])
            ->avg(fn ($i) => $i->fecha_reporte->diffInMinutes($i->fecha_resolucion));

        return $promedio ? round($promedio / 60, 1) : null;
    }
}
