<?php

namespace App\Http\Controllers;

use App\Models\Garantia;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Auditor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Plan de mantenimiento preventivo de un proyecto o servicio: genera las visitas
 * («Mantenimiento 1 de 4», «2 de 4»…) con su fecha, y avisa cuando se acercan.
 */
class PlanMantenimientoController extends Controller
{
    public function create(Request $request)
    {
        $proyecto = Proyecto::find($request->proyecto_id);
        $garantia = $proyecto?->garantias()->activas()->orderByDesc('fecha_vencimiento')->first();
        $inicio = $proyecto ? max($proyecto->fecha_entrega, today())->copy()->addMonthsNoOverflow(3) : today()->addMonth();
        $visitas = 4;

        // Sugerencia dentro de la vigencia de la garantía: primera visita y N° de visitas que caben.
        if ($garantia) {
            if ($inicio->gt($garantia->fecha_vencimiento)) {
                $inicio = today()->addWeek()->min($garantia->fecha_vencimiento);
            }
            $visitas = max(1, min($visitas, (int) floor($inicio->diffInMonths($garantia->fecha_vencimiento) / 3) + 1));
        }

        return view('planes.form', $this->datosFormulario(new PlanMantenimiento([
            'proyecto_id' => $proyecto?->id,
            'garantia_id' => $garantia?->id,
            'nombre' => 'Mantenimiento preventivo',
            'frecuencia_meses' => 3,
            'total_visitas' => $visitas,
            'fecha_inicio' => $inicio,
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
            'garantias' => Garantia::whereIn('proyecto_id', $proyectos->pluck('id'))
                ->where(fn ($q) => $q->activas()->orWhere('id', $plan->garantia_id))
                ->orderByDesc('fecha_vencimiento')->get()
                ->map(fn ($g) => [
                    'id' => $g->id, 'proyecto_id' => $g->proyecto_id,
                    'texto' => "{$g->codigo} · {$g->tipo} (vence {$g->fecha_vencimiento->format('d/m/Y')})",
                    'desde' => $g->fecha_inicio->format('Y-m-d'), 'hasta' => $g->fecha_vencimiento->format('Y-m-d'),
                ]),
            'tecnicos' => User::activos()->permission('hojas.registrar')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validar(Request $request, ?PlanMantenimiento $plan = null): array
    {
        $realizadas = $plan?->mantenimientos()->where('estado', 'realizado')->max('numero') ?? 0;

        $datos = $request->validate([
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

        $this->validarVigencia($datos);

        return $datos;
    }

    /** Las visitas deben caer dentro de la vigencia de la garantía asociada. */
    private function validarVigencia(array $datos): void
    {
        $garantia = isset($datos['garantia_id']) ? Garantia::find($datos['garantia_id']) : null;
        if (! $garantia) {
            return;
        }

        $vence = $garantia->fecha_vencimiento;
        $inicio = Carbon::parse($datos['fecha_inicio']);
        $frecuencia = (int) $datos['frecuencia_meses'];

        if ($inicio->gt($vence)) {
            throw ValidationException::withMessages([
                'fecha_inicio' => "La garantía {$garantia->codigo} vence el {$vence->format('d/m/Y')}; la primera visita no puede ser posterior.",
            ]);
        }

        $ultima = $inicio->copy()->addMonthsNoOverflow(($datos['total_visitas'] - 1) * $frecuencia);
        if ($ultima->gt($vence)) {
            $caben = 0;
            while ($inicio->copy()->addMonthsNoOverflow($caben * $frecuencia)->lte($vence)) {
                $caben++;
            }

            throw ValidationException::withMessages([
                'total_visitas' => "La garantía {$garantia->codigo} vence el {$vence->format('d/m/Y')}: con visitas cada $frecuencia ".($frecuencia === 1 ? 'mes' : 'meses')
                    ." solo caben $caben dentro de la vigencia (la visita {$datos['total_visitas']} caería el {$ultima->format('d/m/Y')}).",
            ]);
        }
    }
}
