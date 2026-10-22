<?php

namespace App\Services;

use App\Enums\EstadoHoja;
use App\Enums\EstadoIncidencia;
use App\Enums\ResultadoAtencion;
use App\Models\AlmacenArticulo;
use App\Models\Equipo;
use App\Models\EquipoEvento;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\ActaConformidadRecibida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Hoja de servicio = registro de cada visita del técnico.
 * - En una incidencia crea la atención técnica y avanza su estado.
 * - Si el trabajo no se terminó, deja lo pendiente y la fecha de la próxima visita;
 *   la siguiente visita es otra hoja, así queda guardado lo que se hizo cada día.
 * - La conformidad del cliente es el acta firmada (foto o PDF) que se sube a la hoja.
 */
class HojaServicioService
{
    public function __construct(private IncidenciaService $incidencias) {}

    /**
     * @param  array<int, array>  $equipos
     * @param  array<int, array>  $materiales
     * @param  array<int, UploadedFile>  $fotos
     * @param  array<int, UploadedFile>  $actas  acta de conformidad firmada (si el cliente ya la firmó)
     * @param  array<int, string|null>  $descripciones  leyenda de cada foto (aparece en el PDF)
     */
    public function crear(array $datos, array $equipos = [], array $materiales = [], array $fotos = [], array $actas = [], string $origen = 'web', array $descripciones = []): HojaServicio
    {
        $datos = $this->normalizar($this->completarDesdeIncidencia($datos));

        return DB::transaction(function () use ($datos, $equipos, $materiales, $fotos, $actas, $origen, $descripciones) {
            $hoja = HojaServicio::create([
                ...$datos,
                'tecnico_id' => auth()->id(),
                'estado' => EstadoHoja::Registrada,
                'origen' => $origen,
            ]);

            $this->guardarDetalle($hoja, $equipos, $materiales);

            foreach ($fotos as $i => $foto) {
                ArchivoService::guardar($foto, $hoja, ArchivoService::categoriaPara($foto, 'foto'), $descripciones[$i] ?? null);
            }

            if ($hoja->incidencia) {
                $this->incidencias->historial($hoja->incidencia, 'hoja_servicio', null, null, "Visita del {$hoja->fecha->format('d/m/Y')}: hoja {$hoja->numero}");
                $this->registrarAtencion($hoja);
                $this->programarSiguienteVisita($hoja);
            }

            // Visita de un plan de mantenimiento: queda como realizada con esta hoja.
            if ($hoja->mantenimiento) {
                $hoja->mantenimiento->update([
                    'estado' => 'realizado', 'hoja_servicio_id' => $hoja->id, 'fecha_ejecucion' => $hoja->fecha, 'tecnico_id' => $hoja->tecnico_id,
                ]);
            }

            Auditor::log('crear', 'hojas_servicio', "Registró la hoja de servicio {$hoja->numero}", $hoja);

            foreach ($actas as $acta) {
                ArchivoService::guardar($acta, $hoja, 'acta_conformidad', 'Acta de conformidad firmada');
            }
            if ($actas) {
                $this->registrarActa($hoja);
            }

            return $hoja->fresh();
        });
    }

    public function actualizar(HojaServicio $hoja, array $datos, array $equipos, array $materiales): HojaServicio
    {
        $this->exigirEditable($hoja);
        $datos = $this->normalizar($this->completarDesdeIncidencia($datos));

        DB::transaction(function () use ($hoja, $datos, $equipos, $materiales) {
            $hoja->update($datos);
            $hoja->equipos()->delete();
            $hoja->materiales()->delete();
            $this->guardarDetalle($hoja, $equipos, $materiales);

            // La atención de la incidencia y la próxima visita reflejan lo corregido en la hoja
            $hoja->atencion?->update($this->datosAtencion($hoja->fresh()));
            if ($hoja->incidencia && $hoja->incidencia->estaAbierta()) {
                $this->programarSiguienteVisita($hoja->fresh());
            }
        });

        Auditor::log('editar', 'hojas_servicio', "Editó la hoja de servicio {$hoja->numero}", $hoja);

        return $hoja;
    }

    /**
     * Se subió el acta firmada por el cliente: la hoja queda conforme y ya no se edita.
     * Se llama al adjuntar un archivo de categoría «acta de conformidad» a la hoja.
     */
    public function registrarActa(HojaServicio $hoja): void
    {
        if ($hoja->estado !== EstadoHoja::Registrada) {
            return;
        }

        $hoja->update(['estado' => EstadoHoja::Conforme, 'conforme' => true, 'fecha_firma' => now()]);

        if ($hoja->incidencia) {
            $this->incidencias->historial($hoja->incidencia, 'hoja_servicio', null, null, "Acta de conformidad de la hoja {$hoja->numero} recibida");
        }

        Auditor::log('adjuntar', 'hojas_servicio', "Subió el acta de conformidad de la hoja {$hoja->numero}", $hoja);

        $supervisores = User::activos()->permission('hojas.ver')->get()->reject(fn ($u) => $u->id === auth()->id());
        Notification::send($supervisores, new ActaConformidadRecibida($hoja));
    }

    public function anular(HojaServicio $hoja, string $motivo): HojaServicio
    {
        if ($hoja->estado === EstadoHoja::Anulada) {
            throw ValidationException::withMessages(['estado' => 'La hoja ya está anulada.']);
        }

        $hoja->update(['estado' => EstadoHoja::Anulada, 'motivo_anulacion' => $motivo]);
        AlmacenService::devolverPorHoja($hoja, 'hoja anulada');

        // La intervención deja de contar en el historial de los equipos y la visita vuelve a quedar pendiente.
        EquipoEvento::where('hoja_servicio_id', $hoja->id)->delete();
        Mantenimiento::where('hoja_servicio_id', $hoja->id)->update(['estado' => 'programado', 'hoja_servicio_id' => null, 'fecha_ejecucion' => null]);

        if ($hoja->incidencia) {
            $this->incidencias->historial($hoja->incidencia, 'hoja_servicio', null, null, "Hoja {$hoja->numero} anulada: $motivo");
        }

        Auditor::log('anular', 'hojas_servicio', "Anuló la hoja de servicio {$hoja->numero}: $motivo", $hoja);

        return $hoja;
    }

    /**
     * Si el trabajo quedó pendiente, la incidencia sigue en proceso con la fecha de la próxima visita;
     * si se resolvió, se limpia la reprogramación.
     */
    private function programarSiguienteVisita(HojaServicio $hoja): void
    {
        $inc = $hoja->incidencia->fresh();

        if ($hoja->requiereVisita() && $hoja->proxima_visita) {
            $cambio = $inc->proxima_visita?->toDateString() !== $hoja->proxima_visita->toDateString();
            $inc->update(['proxima_visita' => $hoja->proxima_visita, 'motivo_reprogramacion' => $hoja->pendientes]);

            if ($cambio) {
                $this->incidencias->historial($inc, 'reprogramacion', null, null,
                    "Continúa el {$hoja->proxima_visita->format('d/m/Y')}".($hoja->pendientes ? ": {$hoja->pendientes}" : ''));
            }
        } elseif ($inc->proxima_visita && $hoja->resultado && ! $hoja->requiereVisita()) {
            $inc->update(['proxima_visita' => null, 'motivo_reprogramacion' => null]);
        }
    }

    /** Si viene de una incidencia, el proyecto y la garantía se toman de ella. */
    private function completarDesdeIncidencia(array $datos): array
    {
        if (! empty($datos['incidencia_id'])) {
            $inc = Incidencia::findOrFail($datos['incidencia_id']);
            $datos['proyecto_id'] = $inc->proyecto_id;
            $datos['garantia_id'] = $inc->garantia_id;
        }

        if (! empty($datos['garantia_id']) && ! Proyecto::find($datos['proyecto_id'])?->garantias()->whereKey($datos['garantia_id'])->exists()) {
            throw ValidationException::withMessages(['garantia_id' => 'La garantía no pertenece al proyecto.']);
        }

        return $datos;
    }

    /** Limpia verificaciones y mediciones vacías; sin trabajo pendiente no hay próxima visita. */
    private function normalizar(array $datos): array
    {
        if (array_key_exists('verificaciones', $datos)) {
            $datos['verificaciones'] = collect($datos['verificaciones'] ?? [])
                ->filter(fn ($v) => filled($v['item'] ?? null) && filled($v['resultado'] ?? null))
                ->map(fn ($v) => ['item' => $v['item'], 'resultado' => $v['resultado']])->values()->all() ?: null;
        }

        if (array_key_exists('mediciones', $datos)) {
            $datos['mediciones'] = collect($datos['mediciones'] ?? [])
                ->filter(fn ($m) => filled($m['parametro'] ?? null) && filled($m['valor'] ?? null))
                ->map(fn ($m) => ['parametro' => $m['parametro'], 'valor' => $m['valor'], 'unidad' => $m['unidad'] ?? null])->values()->all() ?: null;
        }

        $resultado = ResultadoAtencion::tryFrom($datos['resultado'] ?? '');
        if (! $resultado?->requiereVisita()) {
            $datos['proxima_visita'] = null;
        }

        return $datos;
    }

    private function guardarDetalle(HojaServicio $hoja, array $equipos, array $materiales): void
    {
        $hoja->equipos()->createMany(collect($equipos)
            ->map(fn ($e) => $this->completarEquipo($e))
            ->filter(fn ($e) => filled($e['equipo'] ?? null))->values()->all());
        $hoja->materiales()->createMany(collect($materiales)
            ->map(fn ($m) => $this->completarMaterial($m))
            ->filter(fn ($m) => filled($m['descripcion'] ?? null))
            ->map(fn ($m) => [...$m, 'cantidad' => $m['cantidad'] ?? 1, 'unidad' => $m['unidad'] ?? 'und'])->values()->all());

        // Lo que se tomó del almacén se descuenta del stock
        AlmacenService::descontarPorHoja($hoja);

        EquipoService::alGuardarHoja($hoja->load(['equipos', 'materiales']));
    }

    /**
     * En una incidencia asignada o en proceso, la hoja crea la atención técnica
     * (diagnóstico, trabajo y resultado) y avanza su estado.
     */
    private function registrarAtencion(HojaServicio $hoja): void
    {
        $inc = $hoja->incidencia;
        if (! $hoja->resultado || ! in_array($inc->estado, [EstadoIncidencia::Asignada, EstadoIncidencia::EnProceso])) {
            return;
        }

        if ($inc->estado === EstadoIncidencia::Asignada) {
            $this->incidencias->iniciar($inc);
        }

        $atencion = $this->incidencias->registrarAtencion($inc->fresh(), $this->datosAtencion($hoja), [], $hoja->origen, enEquipo: false);
        $hoja->update(['atencion_id' => $atencion->id]);
    }

    private function datosAtencion(HojaServicio $hoja): array
    {
        $dia = $hoja->fecha->toDateString();
        $inicio = Carbon::parse("$dia {$hoja->hora_llegada}");
        $fin = $hoja->hora_salida ? Carbon::parse("$dia {$hoja->hora_salida}") : null;

        return [
            'fecha_inicio' => $inicio->isFuture() ? now() : $inicio,
            'fecha_fin' => $fin && $fin->isFuture() ? now() : $fin,
            'diagnostico' => $hoja->diagnostico ?: $hoja->trabajo_realizado,
            'actividades' => $hoja->trabajo_realizado,
            'observaciones' => collect([$hoja->observaciones, $hoja->pendientes ? "Pendiente: {$hoja->pendientes}" : null])->filter()->implode("\n") ?: null,
            'resultado' => $hoja->resultado,
            'latitud' => $hoja->latitud,
            'longitud' => $hoja->longitud,
        ];
    }

    /** Si el material es del almacén, su nombre y unidad salen del artículo. */
    private function completarMaterial(array $fila): array
    {
        $articulo = ! empty($fila['articulo_id']) ? AlmacenArticulo::find($fila['articulo_id']) : null;
        if (! $articulo) {
            return [...$fila, 'articulo_id' => null];
        }

        return [...$fila, 'descripcion' => $articulo->nombreCompleto(), 'unidad' => $articulo->unidad];
    }

    /** Si se eligió un equipo del inventario, sus datos completan la fila. */
    private function completarEquipo(array $fila): array
    {
        // Checklist: solo los puntos con resultado
        $fila['checklist'] = collect($fila['checklist'] ?? [])
            ->filter(fn ($p) => filled($p['item'] ?? null) && filled($p['resultado'] ?? null))
            ->map(fn ($p) => ['item' => $p['item'], 'resultado' => $p['resultado'], 'nota' => $p['nota'] ?? null])
            ->values()->all() ?: null;

        $equipo = ! empty($fila['equipo_id']) ? Equipo::find($fila['equipo_id']) : null;
        if (! $equipo) {
            return [...$fila, 'equipo_id' => null];
        }

        return [
            ...$fila,
            'equipo' => filled($fila['equipo'] ?? null) ? $fila['equipo'] : $equipo->categoria,
            'marca' => $fila['marca'] ?? null ?: $equipo->marca,
            'modelo' => $fila['modelo'] ?? null ?: $equipo->modelo,
            'serie' => $fila['serie'] ?? null ?: $equipo->serie,
            'ubicacion' => $fila['ubicacion'] ?? null ?: $equipo->ubicacion,
        ];
    }

    private function exigirEditable(HojaServicio $hoja): void
    {
        if (! $hoja->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'La hoja ya tiene el acta de conformidad o está anulada y no puede modificarse.']);
        }
    }
}
