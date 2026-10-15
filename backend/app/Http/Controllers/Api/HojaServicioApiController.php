<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HojaServicioRequest;
use App\Http\Resources\HojaServicioResource;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Services\HojaServicioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HojaServicioApiController extends Controller
{
    public function __construct(private HojaServicioService $servicio) {}

    /** Hojas registradas por el técnico autenticado. */
    public function index(Request $request)
    {
        abort_unless($request->user()->can('hojas.registrar'), 403);

        $hojas = HojaServicio::where('tecnico_id', $request->user()->id)
            ->with(['proyecto.entidad', 'proyecto.clienteFinal'])
            ->filtrar($request->only('estado', 'buscar'))
            ->latest('fecha')->latest('id')
            ->paginate(20);

        return HojaServicioResource::collection($hojas);
    }

    public function show(HojaServicio $hoja)
    {
        Gate::authorize('view', $hoja);

        return new HojaServicioResource($hoja->load(['proyecto.entidad', 'proyecto.clienteFinal', 'incidencia', 'mantenimiento.plan', 'tecnico', 'equipos', 'materiales', 'archivos']));
    }

    /** multipart/form-data: datos, equipos[i][campo], materiales[i][campo], fotos[], firma (trazos JSON). */
    public function store(HojaServicioRequest $request)
    {
        abort_unless($request->user()->can('hojas.registrar'), 403);

        if ($request->incidencia_id) {
            Gate::authorize('view', Incidencia::findOrFail($request->incidencia_id));
        }

        $hoja = $this->servicio->crear(
            $request->datosHoja(),
            $request->input('equipos', []),
            $request->input('materiales', []),
            $request->file('fotos', []),
            $request->firma(),
            'movil',
        );

        return $this->show($hoja)->response()->setStatusCode(201);
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

        return $this->show($hoja->fresh());
    }

    /** Artículos activos del almacén para los materiales de la hoja. */
    public function almacen(Request $request)
    {
        abort_unless($request->user()->can('hojas.registrar'), 403);

        return \App\Models\AlmacenArticulo::where('activo', true)->orderBy('nombre')->get()
            ->map(fn ($a) => ['id' => $a->id, 'codigo' => $a->codigo, 'nombre' => $a->nombreCompleto(), 'unidad' => $a->unidad, 'stock' => $a->stock, 'tipo' => $a->tipo]);
    }

    /** Checklists de mantenimiento: { categoría normalizada: [puntos] }. */
    public function checklists(Request $request)
    {
        abort_unless($request->user()->can('hojas.registrar'), 403);

        return response()->json((object) \App\Models\Checklist::mapa());
    }

    /** Proyectos operativos con sus garantías, para hojas de mantenimiento preventivo. */
    public function proyectos(Request $request)
    {
        abort_unless($request->user()->can('hojas.registrar'), 403);

        return Proyecto::with(['entidad', 'clienteFinal', 'garantias' => fn ($q) => $q->activas()])
            ->where('estado', 'operativo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Proyecto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'entidad' => $p->lugarDeServicio()->nombre,
                'garantias' => $p->garantias->map(fn ($g) => ['id' => $g->id, 'texto' => "{$g->codigo} · {$g->tipo}"])->values(),
            ]);
    }
}
