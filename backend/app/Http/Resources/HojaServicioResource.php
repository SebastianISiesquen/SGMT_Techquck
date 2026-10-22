<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** Formato JSON de la hoja de servicio para la app móvil. */
class HojaServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'tipo_servicio' => $this->tipo_servicio->value,
            'tipo_servicio_label' => $this->tipo_servicio->label(),
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'fecha' => $this->fecha->toDateString(),
            'hora_llegada' => substr($this->hora_llegada, 0, 5),
            'hora_salida' => $this->hora_salida ? substr($this->hora_salida, 0, 5) : null,
            'diagnostico' => $this->diagnostico,
            'resultado' => $this->resultado,
            'resultado_label' => $this->resultadoLabel(),
            'trabajo_realizado' => $this->trabajo_realizado,
            'observaciones' => $this->observaciones,
            'recomendaciones' => $this->recomendaciones,
            'conforme' => $this->conforme,
            'receptor_nombre' => $this->receptor_nombre,
            'receptor_dni' => $this->receptor_dni,
            'receptor_cargo' => $this->receptor_cargo,
            'tiene_acta' => $this->tieneActa(),
            'fecha_acta' => $this->fecha_firma?->toIso8601String(),
            'puede_subir_acta' => $this->estado->value !== 'anulada' && ($this->tecnico_id === $request->user()?->id || ($request->user()?->can('hojas.ver') ?? false)),
            'pendientes' => $this->pendientes,
            'proxima_visita' => $this->proxima_visita?->toDateString(),
            'verificaciones' => $this->verificaciones ?? [],
            'mediciones' => $this->mediciones ?? [],
            'proyecto' => ['id' => $this->proyecto->id, 'nombre' => $this->proyecto->nombre, 'entidad' => $this->proyecto->lugarDeServicio()->nombre],
            'mantenimiento' => $this->whenLoaded('mantenimiento', fn () => $this->mantenimiento?->etiqueta()),
            'incidencia' => $this->whenLoaded('incidencia', fn () => $this->incidencia ? ['id' => $this->incidencia->id, 'codigo' => $this->incidencia->codigo] : null),
            'tecnico' => $this->whenLoaded('tecnico', fn () => $this->tecnico?->name),
            'equipos' => $this->whenLoaded('equipos', fn () => $this->equipos->map(fn ($e) => [
                'equipo_id' => $e->equipo_id, 'equipo' => $e->equipo, 'marca' => $e->marca, 'modelo' => $e->modelo, 'serie' => $e->serie,
                'accion' => $e->accionLabel(), 'estado_final' => $e->estadoLabel(), 'observacion' => $e->observacion,
                'checklist' => $e->checklist ?? [],
            ])),
            'materiales' => $this->whenLoaded('materiales', fn () => $this->materiales->map(fn ($m) => [
                'descripcion' => $m->descripcion, 'cantidad' => $m->cantidad, 'unidad' => $m->unidad,
            ])),
            'archivos' => $this->whenLoaded('archivos', fn () => $this->archivos->map(fn ($f) => IncidenciaResource::archivo($f))),
            // Enlace temporal (30 min) para abrir el PDF en el navegador del teléfono sin token
            'pdf_url' => URL::temporarySignedRoute('hojas.pdf-publico', now()->addMinutes(30), ['hoja' => $this->id]),
        ];
    }
}
