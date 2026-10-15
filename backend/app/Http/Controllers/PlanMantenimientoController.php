<?php

namespace App\Http\Controllers;

use App\Models\Garantia;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Plan de mantenimiento preventivo de un proyecto o servicio: genera las visitas
 * («Mantenimiento 1 de 4», «2 de 4»…) con su fecha, y avisa cuando se acercan.
 */
class PlanMantenimientoController extends Controller
{
    public function create(Request $request)
    {
        $proyecto = Proyecto::find($request->proyecto_id);

        return view('planes.form', $this->datosFormulario(new PlanMantenimiento([
            'proyecto_id' => $proyecto?->id,
            'garantia_id' => $proyecto?->garantias()->activas()->orderByDesc('fecha_vencimiento')->value('id'),
            'nombre' => 'Mantenimiento preventivo',
            'frecuencia_meses' => 3,
            'total_visitas' => 4,
            'fecha_inicio' => $proyecto ? max($proyecto->fecha_entrega, today())->copy()->addMonthsNoOverflow(3) : today()->addMonth(),
        ])));
    }

    public function store(Request $request)
    {
        $plan = DB::transaction(function () use ($request) {
            $plan = PlanMantenimiento::create($this->validar($request));
            $plan->programarVisitas();

            return $plan;
        });

        Auditor::log('crear', 'mantenimientos', "Programó «{$plan->nombre}» ({$plan->total_visitas} visitas) para {$plan->proyecto->codigo}", $plan->proyecto);

        return redirect()->route('planes.show', $plan)->with('ok', "Se programaron {$plan->total_visitas} visitas de mantenimiento.");
    }

    public function show(PlanMantenimiento $plan)
    {
        $plan->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'tecnico', 'mantenimientos' => fn ($q) => $q->with(['tecnico', 'hojaServicio'])]);

        return view('planes.show', [
            'plan' => $plan,
            'tecnicos' => User::activos()->permission('hojas.registrar')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function edit(PlanMantenimiento $plan)
    {
        return view('planes.form', $this->datosFormulario($plan));
    }

    public function update(Request $request, PlanMantenimiento $plan)
    {
        DB::transaction(function () use ($request, $plan) {
            $plan->fill($this->validar($request, $plan));
            $recalcular = $plan->isDirty(['fecha_inicio', 'frecuencia_meses']);
            $plan->save();
            $plan->programarVisitas($recalcular);
        });

        Auditor::log('editar', 'mantenimientos', "Editó el plan «{$plan->nombre}» de {$plan->proyecto->codigo}", $plan->proyecto);

        return redirect()->route('planes.show', $plan)->with('ok', 'Plan actualizado. Las visitas ya realizadas no se modificaron.');
    }

    public function destroy(PlanMantenimiento $plan)
    {
        if ($plan->mantenimientos()->where('estado', 'realizado')->exists()) {
            $plan->update(['activo' => false]);
            $plan->mantenimientos()->where('estado', 'programado')->update(['estado' => 'cancelado']);
            $mensaje = 'El plan tenía visitas realizadas: se desactivó y se cancelaron las pendientes.';
        } else {
            $plan->delete();
            $mensaje = 'Plan eliminado.';
        }

        Auditor::log('eliminar', 'mantenimientos', "Retiró el plan «{$plan->nombre}» de {$plan->proyecto->codigo}", $plan->proyecto);

        return redirect()->route('proyectos.show', $plan->proyecto_id)->with('ok', $mensaje);
    }

    private function datosFormulario(PlanMantenimiento $plan): array
    {
        $proyectos = Proyecto::with('entidad')->enCurso()->orWhere('id', $plan->proyecto_id)->orderBy('nombre')->get();

        return [
            'plan' => $plan,
            'proyectos' => $proyectos->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->entidad->nombre})"]),
            'garantias' => Garantia::whereIn('proyecto_id', $proyectos->pluck('id'))->get()
                ->map(fn ($g) => ['id' => $g->id, 'proyecto_id' => $g->proyecto_id, 'texto' => "{$g->codigo} · {$g->tipo} (vence {$g->fecha_vencimiento->format('d/m/Y')})"]),
            'tecnicos' => User::activos()->permission('hojas.registrar')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validar(Request $request, ?PlanMantenimiento $plan = null): array
    {
        $realizadas = $plan?->mantenimientos()->where('estado', 'realizado')->max('numero') ?? 0;

        return $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'garantia_id' => ['nullable', Rule::exists('garantias', 'id')->where('proyecto_id', $request->proyecto_id)],
            'nombre' => ['required', 'string', 'max:255'],
            'frecuencia_meses' => ['required', 'integer', 'min:1', 'max:24'],
            'total_visitas' => ['required', 'integer', 'min:'.max(1, $realizadas), 'max:60'],
            'fecha_inicio' => ['required', 'date'],
            'tecnico_id' => ['nullable', 'exists:users,id'],
            'alcance' => ['nullable', 'string', 'max:3000'],
        ], [
            'total_visitas.min' => "Ya se realizaron $realizadas visitas; el total no puede ser menor.",
        ]);
    }
}
