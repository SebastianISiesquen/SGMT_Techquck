<?php

namespace App\Services;

use App\Enums\EstadoIncidencia;
use App\Enums\ResultadoAtencion;
use App\Models\Atencion;
use App\Models\Incidencia;
use App\Models\User;
use App\Notifications\IncidenciaAsignada;
use App\Notifications\IncidenciaResuelta;
use App\Support\Ajustes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de la incidencia y su trazabilidad (RF07–RF11):
 * Registrada → Asignada → En proceso → Resuelta → Cerrada  (o Anulada)
 */
class IncidenciaService
{
    public function registrar(array $datos): Incidencia
    {
        return DB::transaction(function () use ($datos) {
            $incidencia = Incidencia::create([
                ...$datos,
                'estado' => EstadoIncidencia::Registrada,
                'registrado_por' => auth()->id(),
            ]);

            $this->historial($incidencia, 'registro', null, EstadoIncidencia::Registrada, 'Incidencia registrada');
            EquipoService::alRegistrarIncidencia($incidencia);
            Auditor::log('crear', 'incidencias', "Registró la incidencia {$incidencia->codigo}", $incidencia);

            if (! empty($datos['tecnico_id'])) {
                $this->asignar($incidencia, User::findOrFail($datos['tecnico_id']), $datos['fecha_limite'] ?? null);
            }

            return $incidencia;
        });
    }

    public function asignar(Incidencia $incidencia, User $tecnico, $fechaLimite = null, ?string $comentario = null): Incidencia
    {
        $this->exigirAbierta($incidencia);

        if (! $tecnico->activo || ! $tecnico->can('atenciones.registrar')) {
            throw ValidationException::withMessages(['tecnico_id' => 'El usuario seleccionado no puede atender incidencias.']);
        }

        $anterior = $incidencia->estado;
        $reasignacion = $incidencia->tecnico_id && $incidencia->tecnico_id !== $tecnico->id;

        $incidencia->update([
            'tecnico_id' => $tecnico->id,
            'asignado_por' => auth()->id(),
            'fecha_asignacion' => now(),
            'fecha_limite' => $fechaLimite ?: $incidencia->fecha_limite,
            'estado' => $anterior === EstadoIncidencia::Registrada ? EstadoIncidencia::Asignada : $anterior,
        ]);

        $detalle = ($reasignacion ? 'Reasignada a ' : 'Asignada a ').$tecnico->name.($comentario ? ". $comentario" : '');
        $this->historial($incidencia, 'asignacion', $anterior, $incidencia->estado, $detalle);
        Auditor::log('asignar', 'incidencias', "{$incidencia->codigo}: $detalle", $incidencia);

        $tecnico->notify(new IncidenciaAsignada($incidencia));

        return $incidencia;
    }

    /** El técnico indica que comenzó a trabajar en la incidencia. */
    public function iniciar(Incidencia $incidencia): Incidencia
    {
        if ($incidencia->estado !== EstadoIncidencia::Asignada) {
            return $incidencia;
        }

        $incidencia->update(['estado' => EstadoIncidencia::EnProceso]);
        $this->historial($incidencia, 'cambio_estado', EstadoIncidencia::Asignada, EstadoIncidencia::EnProceso, 'El técnico inició la atención');

        return $incidencia;
    }

    /**
     * Registra diagnóstico, actividades, resultado y evidencias (RF09–RF10).
     *
     * @param  array<int, array{file: UploadedFile, categoria: ?string, descripcion: ?string}>  $archivos
     */
    public function registrarAtencion(Incidencia $incidencia, array $datos, array $archivos = [], string $origen = 'web', bool $enEquipo = true): Atencion
    {
        if (! in_array($incidencia->estado, [EstadoIncidencia::Asignada, EstadoIncidencia::EnProceso])) {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden registrar atenciones en incidencias asignadas o en proceso.']);
        }

        return DB::transaction(function () use ($incidencia, $datos, $archivos, $origen, $enEquipo) {
            $atencion = $incidencia->atenciones()->create([
                ...$datos,
                'tecnico_id' => auth()->id(),
                'origen' => $origen,
            ]);

            foreach ($archivos as $a) {
                ArchivoService::guardar(
                    $a['file'],
                    $atencion,
                    ArchivoService::categoriaPara($a['file'], $a['categoria'] ?? null),
                    $a['descripcion'] ?? null,
                );
            }

            $anterior = $incidencia->estado;
            $resultado = ResultadoAtencion::from($datos['resultado']);
            $nuevo = $resultado->finaliza() ? EstadoIncidencia::Resuelta : EstadoIncidencia::EnProceso;

            $incidencia->update([
                'estado' => $nuevo,
                'fecha_resolucion' => $nuevo === EstadoIncidencia::Resuelta ? now() : null,
            ]);

            // Si la atención viene de una hoja de servicio, la hoja ya anota la intervención en el equipo
            if ($enEquipo) {
                EquipoService::alRegistrarAtencion($atencion);
            }

            $detalle = "Resultado: {$resultado->label()}".(count($archivos) ? ' · '.count($archivos).' evidencia(s)' : '');
            $this->historial($incidencia, 'atencion', $anterior, $nuevo, $detalle);
            Auditor::log('atender', 'incidencias', "Registró atención en {$incidencia->codigo} ($detalle)", $incidencia);

            if ($nuevo === EstadoIncidencia::Resuelta) {
                Notification::send($this->supervisores($incidencia), new IncidenciaResuelta($incidencia));
            }

            return $atencion;
        });
    }

    public function cerrar(Incidencia $incidencia, ?string $comentario = null): Incidencia
    {
        if ($incidencia->estado !== EstadoIncidencia::Resuelta) {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden cerrar incidencias en estado Resuelta.']);
        }

        if (Ajustes::int('requerir_acta_cierre') && ! $incidencia->tieneActaConformidad()) {
            throw ValidationException::withMessages(['estado' => 'Para cerrar la incidencia se requiere el Acta de conformidad adjunta o una Hoja de servicio firmada con conformidad.']);
        }

        $incidencia->update([
            'estado' => EstadoIncidencia::Cerrada,
            'fecha_cierre' => now(),
            'cerrado_por' => auth()->id(),
        ]);

        $this->historial($incidencia, 'cierre', EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada, $comentario ?: 'Incidencia cerrada');
        Auditor::log('cerrar', 'incidencias', "Cerró la incidencia {$incidencia->codigo}", $incidencia);

        return $incidencia;
    }

    /** Devuelve la incidencia al técnico (p. ej. la conformidad fue observada). */
    public function reabrir(Incidencia $incidencia, string $motivo): Incidencia
    {
        if (! in_array($incidencia->estado, [EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada])) {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden reabrir incidencias resueltas o cerradas.']);
        }

        $anterior = $incidencia->estado;
        $nuevo = $incidencia->tecnico_id ? EstadoIncidencia::EnProceso : EstadoIncidencia::Registrada;

        $incidencia->update(['estado' => $nuevo, 'fecha_resolucion' => null, 'fecha_cierre' => null, 'cerrado_por' => null]);

        $this->historial($incidencia, 'reapertura', $anterior, $nuevo, $motivo);
        Auditor::log('reabrir', 'incidencias', "Reabrió la incidencia {$incidencia->codigo}: $motivo", $incidencia);

        $incidencia->tecnico?->notify(new IncidenciaAsignada($incidencia, reabierta: true));

        return $incidencia;
    }

    public function anular(Incidencia $incidencia, string $motivo): Incidencia
    {
        if (in_array($incidencia->estado, [EstadoIncidencia::Cerrada, EstadoIncidencia::Anulada])) {
            throw ValidationException::withMessages(['estado' => 'La incidencia ya está cerrada o anulada.']);
        }

        $anterior = $incidencia->estado;
        $incidencia->update(['estado' => EstadoIncidencia::Anulada, 'cerrado_por' => auth()->id(), 'fecha_cierre' => now()]);

        $this->historial($incidencia, 'anulacion', $anterior, EstadoIncidencia::Anulada, $motivo);
        Auditor::log('anular', 'incidencias', "Anuló la incidencia {$incidencia->codigo}: $motivo", $incidencia);

        return $incidencia;
    }

    public function historial(Incidencia $incidencia, string $accion, ?EstadoIncidencia $anterior, ?EstadoIncidencia $nuevo, ?string $detalle = null): void
    {
        $incidencia->historial()->create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'detalle' => $detalle,
        ]);
    }

    private function exigirAbierta(Incidencia $incidencia): void
    {
        if (! $incidencia->estaAbierta()) {
            throw ValidationException::withMessages(['estado' => 'La incidencia ya no está abierta.']);
        }
    }

    /** Quien asignó la incidencia, o en su defecto todos los que pueden cerrar incidencias. */
    private function supervisores(Incidencia $incidencia)
    {
        if ($incidencia->asignador && $incidencia->asignador->activo) {
            return collect([$incidencia->asignador]);
        }

        return User::activos()->permission('incidencias.cerrar')->get();
    }
}
