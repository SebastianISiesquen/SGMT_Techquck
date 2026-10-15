<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGarantia;
use App\Enums\EstadoHoja;
use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Enums\ResultadoAtencion;
use App\Enums\TipoServicio;
use App\Exports\TablaExport;
use App\Models\AlmacenMovimiento;
use App\Models\Atencion;
use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Auditor;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** Generación y exportación de reportes en PDF y Excel (RF15). */
class ReporteController extends Controller
{
    public const TIPOS = [
        'proyectos' => 'Proyectos y servicios',
        'garantias' => 'Garantías',
        'equipos' => 'Inventario de equipos',
        'incidencias' => 'Incidencias',
        'atenciones' => 'Atenciones técnicas',
        'hojas' => 'Hojas de servicio en campo',
        'mantenimientos' => 'Mantenimientos programados',
        'cotizaciones' => 'Cotizaciones',
        'almacen' => 'Movimientos del almacén',
    ];

    public function index()
    {
        return view('reportes.index', [
            'tipos' => self::TIPOS,
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'tecnicos' => User::tecnicos()->pluck('name', 'id'),
            'estadosGarantia' => EstadoGarantia::options(),
            'estadosIncidencia' => EstadoIncidencia::options(),
            'prioridades' => Prioridad::options(),
            'estadosProyecto' => Proyecto::ESTADOS,
            'estadosHoja' => EstadoHoja::options(),
            'tiposServicio' => TipoServicio::options(),
            'estadosEquipo' => Equipo::ESTADOS,
            'estadosMantenimiento' => Mantenimiento::ESTADOS + ['vencido' => 'Atrasado'],
            'estadosCotizacion' => Cotizacion::ESTADOS,
            'categorias' => Ajustes::catalogo('categorias_equipo'),
            'tiposMovimiento' => AlmacenMovimiento::TIPOS,
        ]);
    }

    public function exportar(Request $request, string $tipo, string $formato)
    {
        $filtros = $request->only(['entidad_id', 'estado', 'prioridad', 'tecnico_id', 'tipo_servicio', 'categoria', 'desde', 'hasta']);
        $filas = $this->{$tipo}($filtros);

        $datos = [
            'titulo' => 'Reporte de '.mb_strtolower(self::TIPOS[$tipo]),
            'tipo' => $tipo,
            'filas' => $filas,
            'filtros' => $this->describirFiltros($filtros),
            'empresa' => Ajustes::all(),
            'generado' => now(),
            'usuario' => $request->user()->name,
        ];

        Auditor::log('exportar', 'reportes', "Generó {$datos['titulo']} en ".strtoupper($formato).' ('.$filas->count().' registros)');

        $archivo = "reporte-$tipo-".now()->format('Ymd-His');

        if ($formato === 'excel') {
            return Excel::download(new TablaExport("reportes.tablas.$tipo", $datos, self::TIPOS[$tipo]), "$archivo.xlsx");
        }

        return Pdf::loadView('pdf.reporte', $datos)->setPaper('a4', 'landscape')->download("$archivo.pdf");
    }

    private function proyectos(array $f)
    {
        return Proyecto::with(['entidad', 'clienteFinal', 'responsable'])
            ->withCount(['garantias', 'incidencias', 'incidencias as abiertas_count' => fn ($q) => $q->abiertas()])
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('entidad_id', $v)->orWhere('cliente_final_id', $v)))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_entrega', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_entrega', '<=', $v))
            ->orderBy('fecha_entrega')->get();
    }

    private function garantias(array $f)
    {
        return Garantia::with('proyecto.entidad')
            ->withCount('incidencias')
            ->estado($f['estado'] ?? null)
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('proyecto', fn ($q) => $q->where('entidad_id', $v)))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_vencimiento', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_vencimiento', '<=', $v))
            ->orderBy('fecha_vencimiento')->get();
    }

    private function incidencias(array $f)
    {
        return Incidencia::with(['proyecto.entidad', 'tecnico', 'garantia'])
            ->filtrar($f)
            ->orderBy('fecha_reporte')->get();
    }

    private function atenciones(array $f)
    {
        return Atencion::with(['incidencia.proyecto.entidad', 'tecnico'])
            ->withCount('archivos')
            ->when($f['tecnico_id'] ?? null, fn ($q, $v) => $q->where('tecnico_id', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('incidencia.proyecto', fn ($q) => $q->where('entidad_id', $v)))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('resultado', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_inicio', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_inicio', '<=', $v))
            ->orderBy('fecha_inicio')->get();
    }

    private function hojas(array $f)
    {
        return HojaServicio::with(['proyecto.entidad', 'incidencia', 'tecnico'])
            ->withCount('equipos')
            ->filtrar($f)
            ->orderBy('fecha')->orderBy('id')->get();
    }

    private function equipos(array $f)
    {
        return Equipo::with(['entidad', 'proyecto', 'garantia'])
            ->withCount(['incidencias', 'eventos as mantenimientos_count' => fn ($q) => $q->where('tipo', 'mantenimiento')])
            ->withMax(['eventos as ultimo_mantenimiento' => fn ($q) => $q->where('tipo', 'mantenimiento')], 'fecha')
            ->filtrar($f)
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_instalacion', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_instalacion', '<=', $v))
            ->orderBy('entidad_id')->orderBy('codigo')->get();
    }

    private function mantenimientos(array $f)
    {
        return Mantenimiento::with(['plan.proyecto.entidad', 'plan.proyecto.clienteFinal', 'tecnico', 'hojaServicio'])
            ->filtrar($f)
            ->orderBy('fecha_programada')->get();
    }

    private function cotizaciones(array $f)
    {
        return Cotizacion::with(['entidad', 'incidencia', 'equipo', 'autor'])
            ->filtrar($f)
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->orderBy('fecha')->get();
    }

    private function almacen(array $f)
    {
        return AlmacenMovimiento::with(['articulo', 'usuario', 'hojaServicio', 'proyecto.entidad'])
            ->filtrar([...$f, 'tipo' => $f['estado'] ?? null])
            ->orderBy('fecha')->get();
    }

    private function describirFiltros(array $f): array
    {
        $texto = [];
        if ($f['entidad_id'] ?? null) {
            $texto[] = 'Cliente: '.Entidad::find($f['entidad_id'])?->nombre;
        }
        if ($f['estado'] ?? null) {
            $texto[] = 'Estado: '.(EstadoGarantia::tryFrom($f['estado'])?->label()
                ?? EstadoIncidencia::tryFrom($f['estado'])?->label()
                ?? ResultadoAtencion::tryFrom($f['estado'])?->label()
                ?? EstadoHoja::tryFrom($f['estado'])?->label()
                ?? Proyecto::ESTADOS[$f['estado']] ?? Equipo::ESTADOS[$f['estado']] ?? Mantenimiento::ESTADOS[$f['estado']]
                ?? Cotizacion::ESTADOS[$f['estado']] ?? ($f['estado'] === 'vencido' ? 'Atrasado' : $f['estado']));
        }
        if ($f['categoria'] ?? null) {
            $texto[] = 'Categoría: '.$f['categoria'];
        }
        if ($f['prioridad'] ?? null) {
            $texto[] = 'Prioridad: '.Prioridad::tryFrom($f['prioridad'])?->label();
        }
        if ($f['tipo_servicio'] ?? null) {
            $texto[] = 'Servicio: '.TipoServicio::tryFrom($f['tipo_servicio'])?->label();
        }
        if ($f['tecnico_id'] ?? null) {
            $texto[] = 'Técnico: '.User::find($f['tecnico_id'])?->name;
        }
        if (($f['desde'] ?? null) || ($f['hasta'] ?? null)) {
            $texto[] = 'Periodo: '.($f['desde'] ?? '…').' a '.($f['hasta'] ?? '…');
        }

        return $texto;
    }
}
