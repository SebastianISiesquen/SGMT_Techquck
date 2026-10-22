<?php

namespace App\Services;

use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Notifications\InformeCliente;
use App\Services\Pdf\Imagenes;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** Informe periódico al cliente de un proyecto o servicio: lo hecho en el periodo y el estado de sus equipos y garantías. */
class InformeClienteService
{
    public function datos(Proyecto $proyecto, Carbon $desde, Carbon $hasta): array
    {
        $proyecto->loadMissing(['entidad', 'clienteFinal', 'garantias']);
        $rango = [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()];

        $hojas = HojaServicio::with(['tecnico', 'equipos', 'materiales', 'mantenimiento', 'incidencia'])
            ->where('proyecto_id', $proyecto->id)->where('estado', '!=', 'anulada')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')->get();

        $visitas = Mantenimiento::with(['plan', 'tecnico', 'hojaServicio'])
            ->whereHas('plan', fn ($q) => $q->where('proyecto_id', $proyecto->id))
            ->where(fn ($q) => $q->whereBetween('fecha_programada', [$desde->toDateString(), $hasta->toDateString()])
                ->orWhereBetween('fecha_ejecucion', [$desde->toDateString(), $hasta->toDateString()]))
            ->orderBy('fecha_programada')->get();

        $incidencias = Incidencia::with(['equipo', 'tecnico'])
            ->where('proyecto_id', $proyecto->id)
            ->where(fn ($q) => $q->whereBetween('fecha_reporte', $rango)->orWhereBetween('fecha_cierre', $rango)
                ->orWhere(fn ($q) => $q->abiertas()->where('fecha_reporte', '<', $rango[0])))
            ->orderBy('fecha_reporte')->get();

        $equipos = $proyecto->equipos()->with('fotoInstalacion')->orderBy('categoria')->orderBy('codigo')->get();
        // FEI en miniatura para la tabla de equipos instalados
        $feis = $equipos->filter->fotoInstalacion
            ->mapWithKeys(fn ($e) => [$e->id => Imagenes::paraPdf([$e->fotoInstalacion], 64, 48)->first()])
            ->filter();
        $materiales = $hojas->flatMap->materiales->groupBy(fn ($m) => $m->descripcion.'|'.$m->unidad)
            ->map(fn ($g) => ['descripcion' => $g->first()->descripcion, 'unidad' => $g->first()->unidad, 'cantidad' => $g->sum('cantidad')])
            ->values();

        return [
            'p' => $proyecto,
            'desde' => $desde,
            'hasta' => $hasta,
            'empresa' => Ajustes::all(),
            'hojas' => $hojas,
            'visitas' => $visitas,
            'incidencias' => $incidencias,
            'equipos' => $equipos,
            'feis' => $feis,
            'equiposPorEstado' => $equipos->countBy('estado'),
            'equiposAtencion' => $equipos->whereIn('estado', ['con_falla', 'en_reparacion', 'inoperativo', 'observado'])->values(),
            'materiales' => $materiales,
            'garantias' => $proyecto->garantias->where('anulada', false)->values(),
        ];
    }

    public function pdf(Proyecto $proyecto, Carbon $desde, Carbon $hasta)
    {
        return Pdf::loadView('pdf.informe-cliente', $this->datos($proyecto, $desde, $hasta))->setPaper('a4');
    }

    /** Correos del cliente que contrató el servicio (a quien se dirigen los informes). */
    public function destinatarios(Proyecto $proyecto): array
    {
        return $proyecto->entidad->correosAlerta();
    }

    /** Envía el informe por correo; devuelve los destinatarios (vacío si el cliente no tiene correos). */
    public function enviar(Proyecto $proyecto, Carbon $desde, Carbon $hasta): array
    {
        $correos = $this->destinatarios($proyecto);
        if (! $correos) {
            return [];
        }

        $pdf = $this->pdf($proyecto, $desde, $hasta)->output();
        Notification::route('mail', $correos)->notify(new InformeCliente($proyecto, $desde, $hasta, $pdf));
        Auditor::log('notificar', 'proyectos', "Envió el informe {$desde->format('m/Y')} de {$proyecto->codigo} a ".implode(', ', $correos), $proyecto);

        return $correos;
    }
}
