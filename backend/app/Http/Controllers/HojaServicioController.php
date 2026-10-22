<?php

namespace App\Http\Controllers;

use App\Enums\EstadoHoja;
use App\Enums\ResultadoAtencion;
use App\Enums\TipoServicio;
use App\Http\Requests\HojaServicioRequest;
use App\Models\AlmacenArticulo;
use App\Models\Checklist;
use App\Models\Entidad;
use App\Models\HojaServicio;
use App\Models\HojaServicioEquipo;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\ArchivoService;
use App\Services\Pdf\Imagenes;
use App\Services\HojaServicioService;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Hojas de servicio en campo (web). */
class HojaServicioController extends Controller
{
    public function __construct(private HojaServicioService $servicio) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('hojas.ver') || $user->can('hojas.registrar'), 403);

        $hojas = HojaServicio::query()
            ->with(['proyecto.entidad', 'tecnico', 'incidencia'])
            ->unless($user->can('hojas.ver'), fn ($q) => $q->where('tecnico_id', $user->id))
            ->filtrar($request->all())
            ->latest('fecha')->latest('id')
            ->paginate(15)->withQueryString();

        return view('hojas.index', [
            'hojas' => $hojas,
            'verTodas' => $user->can('hojas.ver'),
            'estados' => EstadoHoja::options(),
            'tipos' => TipoServicio::options(),
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'tecnicos' => User::activos()->permission('hojas.registrar')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        $incidencia = $request->incidencia_id ? Incidencia::with(['proyecto.entidad', 'atenciones', 'equipo'])->findOrFail($request->incidencia_id) : null;
        if ($incidencia) {
            Gate::authorize('view', $incidencia);
        }

        // Visita de un plan de mantenimiento programado
        $mantenimiento = ! $incidencia && $request->mantenimiento_id
            ? Mantenimiento::with('plan.proyecto.entidad')->where('estado', 'programado')->findOrFail($request->mantenimiento_id)
            : null;

        // Prellenado desde la última atención registrada de la incidencia
        $ultima = $incidencia?->atenciones->first();
        $hoja = new HojaServicio([
            'incidencia_id' => $incidencia?->id,
            'proyecto_id' => $incidencia?->proyecto_id ?? $mantenimiento?->plan->proyecto_id ?? $request->proyecto_id,
            'garantia_id' => $incidencia?->garantia_id ?? $mantenimiento?->plan->garantia_id,
            'mantenimiento_id' => $mantenimiento?->id,
            'tipo_servicio' => $incidencia ? TipoServicio::Correctivo : TipoServicio::Preventivo,
            'fecha' => today(),
            'hora_llegada' => $ultima?->fecha_inicio?->format('H:i') ?? now()->subHour()->format('H:i'),
            'hora_salida' => $ultima?->fecha_fin?->format('H:i') ?? now()->format('H:i'),
            'diagnostico' => $ultima?->diagnostico,
            'resultado' => $incidencia ? 'resuelto' : null,
            'trabajo_realizado' => $ultima ? $ultima->actividades
                : ($mantenimiento ? "{$mantenimiento->etiqueta()} — {$mantenimiento->plan->nombre}\n".($mantenimiento->plan->alcance ?? '') : null),
            'observaciones' => $ultima?->observaciones,
            'receptor_nombre' => $incidencia?->reportado_por,
        ]);

        // Segunda visita o más: se parte de lo que quedó pendiente la vez anterior.
        $anterior = $incidencia?->hojasServicio()->where('estado', '!=', 'anulada')->with('equipos.inventario')->latest('fecha')->latest('id')->first();
        if ($anterior) {
            $hoja->fill([
                'tipo_servicio' => $anterior->tipo_servicio,
                'diagnostico' => $anterior->diagnostico,
                'trabajo_realizado' => $anterior->pendientes ? "Continuación de la visita del {$anterior->fecha->format('d/m/Y')}. Pendiente: {$anterior->pendientes}\n" : null,
                'hora_llegada' => now()->subHour()->format('H:i'),
                'hora_salida' => now()->format('H:i'),
                'observaciones' => null,
                'receptor_nombre' => $anterior->receptor_nombre,
                'receptor_cargo' => $anterior->receptor_cargo,
            ]);
        }
        $hoja->verificaciones = collect(Ajustes::catalogo('verificaciones_previas'))->map(fn ($i) => ['item' => $i, 'resultado' => 'ok'])->all();

        // El equipo de la incidencia aparece ya cargado en la lista de equipos intervenidos.
        $equiposIniciales = $incidencia?->equipo ? [[
            'equipo_id' => $incidencia->equipo->id, 'codigo' => $incidencia->equipo->codigo, 'equipo' => $incidencia->equipo->categoria,
            'marca' => $incidencia->equipo->marca, 'modelo' => $incidencia->equipo->modelo, 'serie' => $incidencia->equipo->serie,
            'ubicacion' => $incidencia->equipo->ubicacion, 'accion' => 'reparado', 'estado_final' => 'operativo', 'observacion' => '',
        ]] : null;

        if ($anterior && $anterior->equipos->isNotEmpty()) {
            $equiposIniciales = $anterior->equipos->map(fn ($e) => [
                ...$e->only(['equipo_id', 'equipo', 'marca', 'modelo', 'serie', 'ubicacion', 'accion', 'estado_final']),
                'codigo' => $e->inventario?->codigo, 'observacion' => '', 'checklist' => [],
            ])->all();
        }

        return view('hojas.form', [...$this->datosFormulario($hoja), 'incidencia' => $incidencia, 'mantenimiento' => $mantenimiento,
            'equiposIniciales' => $equiposIniciales, 'anterior' => $anterior]);
    }

    public function store(HojaServicioRequest $request)
    {
        if ($request->incidencia_id) {
            Gate::authorize('view', Incidencia::findOrFail($request->incidencia_id));
        }

        $hoja = $this->servicio->crear(
            $request->datosHoja(),
            $request->input('equipos', []),
            $request->input('materiales', []),
            $request->file('fotos', []),
            $request->file('actas', []),
            'web',
            $request->input('fotos_descripcion', []),
        );

        $mensaje = "Hoja de servicio {$hoja->numero} registrada.";
        if ($hoja->requiereVisita()) {
            $mensaje .= " La incidencia continúa: próxima visita el {$hoja->proxima_visita->format('d/m/Y')}.";
        }
        if (! $hoja->tieneActa()) {
            $mensaje .= ' Imprima la hoja, hágala firmar por el cliente y suba el acta firmada.';
        }

        return redirect()->route('hojas.show', $hoja)->with('ok', $mensaje);
    }

    public function show(HojaServicio $hoja)
    {
        Gate::authorize('view', $hoja);
        $hoja->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'incidencia', 'mantenimiento.plan', 'tecnico', 'equipos.inventario', 'materiales', 'archivos.autor']);

        return view('hojas.show', compact('hoja'));
    }

    public function edit(HojaServicio $hoja)
    {
        Gate::authorize('update', $hoja);
        $hoja->load(['equipos.inventario', 'materiales', 'incidencia.proyecto.entidad', 'mantenimiento.plan']);

        return view('hojas.form', [...$this->datosFormulario($hoja), 'incidencia' => $hoja->incidencia, 'mantenimiento' => $hoja->mantenimiento,
            'equiposIniciales' => null, 'anterior' => null]);
    }

    public function update(HojaServicioRequest $request, HojaServicio $hoja)
    {
        Gate::authorize('update', $hoja);

        $this->servicio->actualizar($hoja, $request->datosHoja(), $request->input('equipos', []), $request->input('materiales', []));

        return redirect()->route('hojas.show', $hoja)->with('ok', 'Hoja de servicio actualizada.');
    }

    public function anular(Request $request, HojaServicio $hoja)
    {
        Gate::authorize('anular', $hoja);
        $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $this->servicio->anular($hoja, $request->motivo);

        return back()->with('ok', 'Hoja de servicio anulada.');
    }

    public function pdf(HojaServicio $hoja)
    {
        Gate::authorize('view', $hoja);

        return $this->generarPdf($hoja)->stream("{$hoja->numero}.pdf");
    }

    /** Descarga con enlace firmado temporal (lo usa la app móvil para abrir el PDF en el navegador). */
    public function pdfPublico(HojaServicio $hoja)
    {
        return $this->generarPdf($hoja)->stream("{$hoja->numero}.pdf");
    }

    private function generarPdf(HojaServicio $hoja)
    {
        $hoja->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'incidencia', 'mantenimiento.plan', 'tecnico', 'equipos.inventario', 'materiales', 'archivos']);

        // Fotos del servicio (sin el acta), reducidas y encuadradas para el registro fotográfico
        $fotos = Imagenes::paraPdf($hoja->archivos->filter(fn ($a) => $a->esImagen() && $a->categoria->value !== 'acta_conformidad')->take(8));

        $visitasPrevias = $hoja->incidencia
            ? $hoja->incidencia->hojasServicio()->where('id', '<', $hoja->id)->where('estado', '!=', 'anulada')->orderBy('fecha')->get()
            : collect();

        return Pdf::loadView('pdf.hoja-servicio', [
            'hoja' => $hoja,
            'fotos' => $fotos,
            'visitasPrevias' => $visitasPrevias,
            'empresa' => Ajustes::all(),
        ])->setPaper('a4');
    }

    private function datosFormulario(HojaServicio $hoja): array
    {
        return [
            'hoja' => $hoja,
            'proyectos' => Proyecto::with('entidad')->where('estado', '!=', 'finalizado')->orWhere('id', $hoja->proyecto_id)
                ->orderBy('nombre')->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->entidad->nombre})"]),
            'tipos' => TipoServicio::options(),
            'acciones' => HojaServicioEquipo::ACCIONES,
            'estadosEquipo' => HojaServicioEquipo::ESTADOS,
            'resultados' => ResultadoAtencion::options(),
            // Checklist de mantenimiento por categoría de equipo (clave normalizada)
            'checklists' => Checklist::mapa(),
            'resultadosChecklist' => Checklist::RESULTADOS,
            'articulos' => AlmacenArticulo::where('activo', true)->orderBy('nombre')->get()
                ->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombreCompleto(), 'unidad' => $a->unidad, 'stock' => $a->stock,
                    'texto' => "{$a->nombreCompleto()} · stock ".AlmacenArticulo::cantidad($a->stock)." {$a->unidad}"]),
        ];
    }
}
