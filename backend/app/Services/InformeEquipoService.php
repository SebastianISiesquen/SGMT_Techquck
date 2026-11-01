<?php

namespace App\Services;

use App\Enums\EstadoHoja;
use App\Models\Archivo;
use App\Models\Equipo;
use App\Models\HojaServicio;
use App\Models\HojaServicioEquipo;
use App\Models\Incidencia;
use App\Services\Pdf\Imagenes;
use Illuminate\Support\Collection;

/**
 * Informe técnico por equipo reparado: en una incidencia con varios equipos, cada uno
 * tiene su propia falla, diagnóstico, trabajo, repuestos, fotos y estado final.
 * Incluye las visitas de la incidencia de la que se separó (si quedó pendiente antes).
 */
class InformeEquipoService
{
    /**
     * @param  iterable<Equipo>  $equipos
     * @return Collection<int, array{equipo: Equipo, visitas: Collection, fotos: Collection, resultado: ?\App\Enums\ResultadoAtencion}>
     */
    public static function informes(Incidencia $incidencia, iterable $equipos): Collection
    {
        $incidencias = collect([$incidencia->id])->merge($incidencia->cadenaDeOrigen()->pluck('id'));
        $hojas = HojaServicio::whereIn('incidencia_id', $incidencias)->where('estado', '!=', EstadoHoja::Anulada)->pluck('id');

        // Atenciones registradas sin hoja de servicio (se aplican a todos los equipos de la incidencia)
        $atenciones = $incidencia->atenciones()->with('tecnico')->whereNotIn('id', HojaServicio::whereIn('id', $hojas)->whereNotNull('atencion_id')->pluck('atencion_id'))
            ->oldest('fecha_inicio')->get();

        return collect($equipos)->map(function (Equipo $equipo) use ($hojas, $atenciones) {
            // Lo que se hizo en este equipo en cada visita, de la más antigua a la más reciente
            $visitas = HojaServicioEquipo::with(['hoja.tecnico', 'hoja.materiales'])
                ->whereIn('hoja_servicio_id', $hojas)->where('equipo_id', $equipo->id)->get()
                ->sortBy(fn ($f) => [$f->hoja->fecha->toDateString(), $f->hoja->hora_llegada, $f->id])->values();

            $fotos = Archivo::where('equipo_id', $equipo->id)->where('archivable_type', (new HojaServicio)->getMorphClass())
                ->whereIn('archivable_id', $hojas)->orderBy('id')->get()->filter->esImagen()->take(8);

            return [
                'equipo' => $equipo->loadMissing('garantia'),
                'visitas' => $visitas,
                'atenciones' => $visitas->isEmpty() ? $atenciones : collect(),
                'fotos' => Imagenes::paraPdf($fotos, 250, 180),
                'resultado' => $visitas->last()?->resultado ?? ($visitas->isEmpty() ? $atenciones->last()?->resultado : null),
            ];
        })->values();
    }
}
