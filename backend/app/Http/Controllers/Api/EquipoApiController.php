<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use Illuminate\Http\Request;

/** Equipos y mantenimientos para la app del técnico. */
class EquipoApiController extends Controller
{
    /** Búsqueda por serie, código, marca o modelo; o los equipos de un proyecto (para la hoja de servicio). */
    public function index(Request $request)
    {
        $u = $request->user();
        abort_unless($u->can('equipos.ver') || $u->can('hojas.registrar'), 403);

        $proyecto = Proyecto::find($request->proyecto_id);

        $equipos = Equipo::with(['entidad', 'proyecto'])
            ->when($proyecto, fn ($q) => $q->where(fn ($q) => $q->where('proyecto_id', $proyecto->id)->orWhere('entidad_id', $proyecto->lugarDeServicio()->id)))
            ->buscar($request->buscar)
            ->when(! $proyecto && ! $request->buscar, fn ($q) => $q->whereRaw('1 = 0'))
            ->where('estado', '!=', 'de_baja')
            ->orderBy('codigo')
            ->limit(50)
            ->get();

        return EquipoResource::collection($equipos);
    }

    public function show(Request $request, Equipo $equipo)
    {
        abort_unless($request->user()->can('equipos.ver'), 403);

        return new EquipoResource($equipo->load(['entidad', 'proyecto', 'garantia', 'eventos' => fn ($q) => $q->with(['usuario', 'incidencia', 'hojaServicio'])->limit(100)]));
    }

    /** Lo que lee el escáner: el QR del sticker (URL con el código), el código EQ-… o la serie. */
    public function porCodigo(Request $request, string $codigo)
    {
        abort_unless($request->user()->can('equipos.ver'), 403);

        $texto = strtoupper(trim(urldecode($codigo)));
        if (preg_match('/EQ-\d{6}/', $texto, $m)) {
            $texto = $m[0];
        }

        $equipo = Equipo::where('codigo', $texto)->first() ?? Equipo::where('serie', $texto)->first();
        abort_unless($equipo, 404, 'No se encontró un equipo con ese código o serie.');

        return $this->show($request, $equipo);
    }

    /** Visitas de mantenimiento asignadas al técnico (pendientes, de la más próxima a la más lejana). */
    public function mantenimientos(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('mantenimientos.ver'), 403);

        return Mantenimiento::with(['plan.proyecto.entidad', 'plan.proyecto.clienteFinal'])
            ->pendientes()
            ->when(! $user->can('mantenimientos.gestionar'), fn ($q) => $q->where('tecnico_id', $user->id))
            ->whereDate('fecha_programada', '<=', today()->addDays(60))
            ->orderBy('fecha_programada')
            ->limit(50)
            ->get()
            ->map(fn (Mantenimiento $m) => [
                'id' => $m->id,
                'etiqueta' => $m->etiqueta(),
                'plan' => $m->plan->nombre,
                'alcance' => $m->plan->alcance,
                'fecha_programada' => $m->fecha_programada->toDateString(),
                'dias_restantes' => $m->diasRestantes(),
                'atrasado' => $m->vencido(),
                'proyecto' => ['id' => $m->plan->proyecto_id, 'codigo' => $m->plan->proyecto->codigo, 'nombre' => $m->plan->proyecto->nombre],
                'cliente' => $m->plan->proyecto->lugarDeServicio()->nombre,
            ]);
    }
}
