<?php

namespace App\Http\Controllers;

use App\Models\Entidad;
use App\Models\Mantenimiento;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Agenda de visitas de mantenimiento: próximas, atrasadas y realizadas. */
class MantenimientoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // El técnico ve solo sus visitas; el supervisor, todas.
        $soloMias = ! $user->can('mantenimientos.gestionar');
        $filtros = [...$request->all(), 'estado' => $request->input('estado', 'pendiente')];

        $mantenimientos = Mantenimiento::query()
            ->with(['plan.proyecto.entidad', 'plan.proyecto.clienteFinal', 'tecnico', 'hojaServicio'])
            ->when($soloMias, fn ($q) => $q->where('tecnico_id', $user->id))
            ->when($filtros['estado'] === 'pendiente', fn ($q) => $q->pendientes())
            ->filtrar(in_array($filtros['estado'], ['pendiente', 'todos']) ? collect($filtros)->except('estado')->all() : $filtros)
            ->when($request->plan_id, fn ($q, $v) => $q->where('plan_id', $v))
            ->orderBy('fecha_programada')
            ->paginate(25)->withQueryString();

        $base = Mantenimiento::query()->when($soloMias, fn ($q) => $q->where('tecnico_id', $user->id));

        return view('mantenimientos.index', [
            'mantenimientos' => $mantenimientos,
            'soloMias' => $soloMias,
            'resumen' => [
                'atrasados' => (clone $base)->pendientes()->whereDate('fecha_programada', '<', today())->count(),
                'semana' => (clone $base)->pendientes()->whereBetween('fecha_programada', [today(), today()->addDays(7)])->count(),
                'mes' => (clone $base)->pendientes()->whereBetween('fecha_programada', [today(), today()->addDays(30)])->count(),
                'realizados' => (clone $base)->where('estado', 'realizado')->whereYear('fecha_ejecucion', today()->year)->count(),
            ],
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'tecnicos' => User::activos()->permission('hojas.registrar')->orderBy('name')->pluck('name', 'id'),
            'estado' => $filtros['estado'],
        ]);
    }

    /** Reprogramar, reasignar técnico o cancelar una visita. */
    public function update(Request $request, Mantenimiento $mantenimiento)
    {
        abort_if($mantenimiento->estado === 'realizado', 422, 'La visita ya fue realizada.');

        $datos = $request->validate([
            'fecha_programada' => ['required', 'date'],
            'tecnico_id' => ['nullable', 'exists:users,id'],
            'estado' => ['required', Rule::in(['programado', 'cancelado'])],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($mantenimiento->fecha_programada->toDateString() !== $datos['fecha_programada']) {
            $datos['alerta_enviada'] = false;
        }

        $mantenimiento->update($datos);
        Auditor::log('editar', 'mantenimientos', "Actualizó {$mantenimiento->etiqueta()} de «{$mantenimiento->plan->nombre}»", $mantenimiento->plan->proyecto);

        return back()->with('ok', 'Visita actualizada.');
    }
}
