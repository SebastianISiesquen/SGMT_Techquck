<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equipo y su hoja de vida para la app móvil. */
class EquipoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // La garantía vigente solo se calcula en el detalle (cuando se cargó el historial).
        $garantia = $this->relationLoaded('eventos') ? $this->garantiaVigente() : null;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'categoria' => $this->categoria,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'serie' => $this->serie,
            'nombre' => $this->nombre(),
            'descripcion' => $this->descripcion,
            'ubicacion' => $this->ubicacion,
            'area' => $this->area,
            'responsable' => $this->responsable,
            'fecha_instalacion' => $this->fecha_instalacion?->toDateString(),
            'estado' => $this->estado,
            'estado_label' => $this->estadoLabel(),
            'cliente' => $this->entidad->nombre,
            'proyecto' => $this->proyecto ? ['id' => $this->proyecto->id, 'codigo' => $this->proyecto->codigo, 'nombre' => $this->proyecto->nombre] : null,
            'garantia' => $this->when($this->relationLoaded('eventos'), fn () => $garantia ? [
                'codigo' => $garantia->codigo,
                'tipo' => $garantia->tipo,
                'fecha_vencimiento' => $garantia->fecha_vencimiento->toDateString(),
                'dias_restantes' => $garantia->diasRestantes(),
            ] : null),
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos->map(fn ($e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'tipo_label' => $e->tipoLabel(),
                'color' => $e->tipoColor(),
                'fecha' => $e->fecha->toIso8601String(),
                'titulo' => $e->titulo,
                'detalle' => $e->detalle,
                'componente' => $e->componente,
                'usuario' => $e->usuario?->name,
                'incidencia' => $e->incidencia?->codigo,
                'hoja' => $e->hojaServicio?->numero,
            ])),
        ];
    }
}
