<?php

namespace App\Http\Resources;

use App\Models\Archivo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Formato JSON de la incidencia para la app móvil. */
class IncidenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proyecto = $this->proyecto;
        // El técnico va al cliente final cuando el proyecto es un subcontrato.
        $lugar = $proyecto->lugarDeServicio();

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'prioridad' => $this->prioridad->value,
            'prioridad_label' => $this->prioridad->label(),
            'canal' => $this->canal,
            'reportado_por' => $this->reportado_por,
            'ubicacion_detalle' => $this->ubicacion_detalle,
            'fecha_reporte' => $this->fecha_reporte?->toIso8601String(),
            'fecha_limite' => $this->fecha_limite?->toIso8601String(),
            'fecha_asignacion' => $this->fecha_asignacion?->toIso8601String(),
            'sla_vencido' => $this->estaVencidaSla(),
            'cobertura' => $this->cobertura,
            'cobertura_label' => $this->coberturaLabel(),
            'puede_atender' => in_array($this->estado->value, ['asignada', 'en_proceso']) && $this->tecnico_id === $request->user()?->id,
            'proyecto' => [
                'id' => $proyecto->id,
                'codigo' => $proyecto->codigo,
                'nombre' => $proyecto->nombre,
                'ubicacion' => $proyecto->ubicacion,
                'entidad' => $lugar->nombre,
                'contratante' => $proyecto->clienteFinal ? $proyecto->entidad->nombre : null,
                'contacto' => $lugar->contacto_nombre,
                'contacto_telefono' => $lugar->contacto_telefono,
            ],
            'equipo' => $this->whenLoaded('equipo', fn () => $this->equipo ? [
                'id' => $this->equipo->id,
                'codigo' => $this->equipo->codigo,
                'categoria' => $this->equipo->categoria,
                'nombre' => $this->equipo->nombre(),
                'ubicacion' => collect([$this->equipo->area, $this->equipo->ubicacion])->filter()->implode(' · ') ?: null,
                'responsable' => $this->equipo->responsable,
                'estado_label' => $this->equipo->estadoLabel(),
                // Antecedentes: lo que otros técnicos encontraron antes en este equipo
                'antecedentes' => $this->equipo->relationLoaded('eventos') ? $this->equipo->eventos->map(fn ($e) => [
                    'fecha' => $e->fecha->toIso8601String(),
                    'tipo_label' => $e->tipoLabel(),
                    'titulo' => $e->titulo,
                    'detalle' => $e->detalle,
                ]) : [],
            ] : null),
            'hojas' => $this->whenLoaded('hojasServicio', fn () => $this->hojasServicio->map(fn ($h) => [
                'id' => $h->id, 'numero' => $h->numero, 'estado' => $h->estado->value, 'estado_label' => $h->estado->label(),
            ])),
            'garantia' => $this->whenLoaded('garantia', fn () => $this->garantia ? [
                'codigo' => $this->garantia->codigo,
                'tipo' => $this->garantia->tipo,
                'cobertura' => $this->garantia->cobertura,
                'condiciones' => $this->garantia->condiciones,
                'fecha_vencimiento' => $this->garantia->fecha_vencimiento->toDateString(),
                'estado' => $this->garantia->estado->value,
                'estado_label' => $this->garantia->estado->label(),
            ] : null),
            'atenciones' => $this->whenLoaded('atenciones', fn () => $this->atenciones->map(fn ($a) => [
                'id' => $a->id,
                'tecnico' => $a->tecnico?->name,
                'fecha_inicio' => $a->fecha_inicio->toIso8601String(),
                'fecha_fin' => $a->fecha_fin?->toIso8601String(),
                'diagnostico' => $a->diagnostico,
                'actividades' => $a->actividades,
                'observaciones' => $a->observaciones,
                'resultado' => $a->resultado->value,
                'resultado_label' => $a->resultado->label(),
                'archivos' => $a->archivos->map(fn ($f) => self::archivo($f)),
            ])),
            'archivos' => $this->whenLoaded('archivos', fn () => $this->archivos->map(fn ($f) => self::archivo($f))),
            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn ($h) => [
                'accion' => $h->accionLabel(),
                'detalle' => $h->detalle,
                'estado_nuevo' => $h->estado_nuevo?->label(),
                'usuario' => $h->usuario?->name,
                'fecha' => $h->created_at->toIso8601String(),
            ])),
        ];
    }

    public static function archivo(Archivo $f): array
    {
        return [
            'id' => $f->id,
            'nombre' => $f->nombre_original,
            'categoria' => $f->categoria->value,
            'categoria_label' => $f->categoria->label(),
            'descripcion' => $f->descripcion,
            'mime' => $f->mime,
            'es_imagen' => $f->esImagen(),
            'tamano' => $f->tamanoLegible(),
            'url' => url("/api/archivos/{$f->id}"),
            'fecha' => $f->created_at->toIso8601String(),
        ];
    }
}
