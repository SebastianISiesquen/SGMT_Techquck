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
use App\Services\FirmaService;
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

        // El equipo de la incidencia aparece ya cargado en la lista de equipos intervenidos.
        $equiposIniciales = $incidencia?->equipo ? [[
            'equipo_id' => $incidencia->equipo->id, 'codigo' => $incidencia->equipo->codigo, 'equipo' => $incidencia->equipo->categoria,
            'marca' => $incidencia->equipo->marca, 'modelo' => $incidencia->equipo->modelo, 'serie' => $incidencia->equipo->serie,
            'ubicacion' => $incidencia->equipo->ubicacion, 'accion' => 'reparado', 'estado_final' => 'operativo', 'observacion' => '',
        ]] : null;

        return view('hojas.form', [...$this->datosFormulario($hoja), 'incidencia' => $incidencia, 'mantenimiento' => $mantenimiento, 'equiposIniciales' => $equiposIniciales]);
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
            $request->firma(),
        );

        return redirect()->route('hojas.show', $hoja)->with('ok', $hoja->estaFirmada()
            ? "Hoja de servicio {$hoja->numero} registrada y firmada."
            : "Hoja de servicio {$hoja->numero} registrada. Queda pendiente la firma de la entidad.");
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

        return view('hojas.form', [...$this->datosFormulario($hoja), 'incidencia' => $hoja->incidencia, 'mantenimiento' => $hoja->mantenimiento, 'equiposIniciales' => null]);
    }

    public function update(HojaServicioRequest $request, HojaServicio $hoja)
    {
        Gate::authorize('update', $hoja);

        $this->servicio->actualizar($hoja, $request->datosHoja(), $request->input('equipos', []), $request->input('materiales', []));

        if ($firma = $request->firma()) {
            $this->servicio->firmar($hoja, $firma);
        }

        return redirect()->route('hojas.show', $hoja)->with('ok', 'Hoja de servicio actualizada.');
    }

    public function firmar(Request $request, HojaServicio $hoja)
    {
        Gate::authorize('update', $hoja);

        $datos = $request->validate([
            'receptor_nombre' => ['required', 'string', 'max:255'],
            'receptor_dni' => \App\Support\Reglas::documento(),
            'receptor_cargo' => ['nullable', 'string', 'max:255'],
            'firma_receptor' => ['required', 'string', 'max:1500000'],
            'firma_tecnico' => ['nullable', 'string', 'max:1500000'],
        ], ['firma_receptor.required' => 'Falta la firma del responsable de la entidad.']);

        $this->servicio->firmar($hoja, [...$datos, 'conforme' => $request->boolean('conforme')]);

        return back()->with('ok', 'Hoja de servicio firmada.');
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

        $fotos = $hoja->archivos->filter->esImagen()->take(6)
            ->map(fn ($a) => ['src' => ArchivoService::contenidoBase64($a), 'nombre' => $a->descripcion ?: ''])
            ->filter(fn ($f) => $f['src']);

        return Pdf::loadView('pdf.hoja-servicio', [
            'hoja' => $hoja,
            'fotos' => $fotos,
            'firmaReceptor' => FirmaService::base64($hoja->firma_receptor),
            'firmaTecnico' => FirmaService::base64($hoja->firma_tecnico),
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
