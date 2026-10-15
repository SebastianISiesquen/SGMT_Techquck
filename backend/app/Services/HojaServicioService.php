<?php

namespace App\Services;

use App\Enums\EstadoHoja;
use App\Enums\EstadoIncidencia;
use App\Models\HojaServicio;
use App\Models\Equipo;
use App\Models\EquipoEvento;
use App\Models\Incidencia;
use App\Models\Mantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\HojaServicioFirmada;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Registro, firma y anulación de hojas de servicio en campo. */
class HojaServicioService
{
    public function __construct(private IncidenciaService $incidencias) {}

    /**
     * @param  array<int, array>  $equipos
     * @param  array<int, array>  $materiales
     * @param  array<int, UploadedFile>  $fotos
     * @param  array|null  $firma  receptor_nombre, receptor_dni, receptor_cargo, firma_receptor, firma_tecnico, conforme
     */
    public function crear(array $datos, array $equipos = [], array $materiales = [], array $fotos = [], ?array $firma = null, string $origen = 'web'): HojaServicio
    {
        $datos = $this->completarDesdeIncidencia($datos);

        // La firma se valida antes de crear nada: si es inválida no queda una hoja a medias
        // (evita duplicados cuando la app reintenta el envío).
        $columnasFirma = $firma && ! empty($firma['firma_receptor']) ? $this->prepararFirma($firma) : null;

        return DB::transaction(function () use ($datos, $equipos, $materiales, $fotos, $columnasFirma, $origen) {
            $hoja = HojaServicio::create([
                ...$datos,
                'tecnico_id' => auth()->id(),
                'estado' => EstadoHoja::PendienteFirma,
                'origen' => $origen,
            ]);

            $this->guardarDetalle($hoja, $equipos, $materiales);

            foreach ($fotos as $foto) {
                ArchivoService::guardar($foto, $hoja, ArchivoService::categoriaPara($foto, 'foto'));
            }

            if ($hoja->incidencia) {
                $this->incidencias->historial($hoja->incidencia, 'hoja_servicio', null, null, "Hoja de servicio {$hoja->numero} registrada");
                $this->registrarAtencion($hoja);
            }

            // Visita de un plan de mantenimiento: queda como realizada con esta hoja.
            if ($hoja->mantenimiento) {
                $hoja->mantenimiento->update([
                    'estado' => 'realizado', 'hoja_servicio_id' => $hoja->id, 'fecha_ejecucion' => $hoja->fecha, 'tecnico_id' => $hoja->tecnico_id,
                ]);
            }

            Auditor::log('crear', 'hojas_servicio', "Registró la hoja de servicio {$hoja->numero}", $hoja);

            if ($columnasFirma) {
                $hoja->update($columnasFirma);
                $this->registrarFirma($hoja);
            }

            return $hoja->fresh();
        });
    }

    public function actualizar(HojaServicio $hoja, array $datos, array $equipos, array $materiales): HojaServicio
    {
        $this->exigirEditable($hoja);
        $datos = $this->completarDesdeIncidencia($datos);

        DB::transaction(function () use ($hoja, $datos, $equipos, $materiales) {
            $hoja->update($datos);
            $hoja->equipos()->delete();
            $hoja->materiales()->delete();
            $this->guardarDetalle($hoja, $equipos, $materiales);

            // La atención de la incidencia refleja lo corregido en la hoja
            $hoja->atencion?->update($this->datosAtencion($hoja->fresh()));
        });

        Auditor::log('editar', 'hojas_servicio', "Editó la hoja de servicio {$hoja->numero}", $hoja);

        return $hoja;
    }

    /** Firma del responsable de la entidad (conformidad) y del técnico. La hoja queda bloqueada. */
    public function firmar(HojaServicio $hoja, array $firma): HojaServicio
    {
        $this->exigirEditable($hoja);

        $hoja->update($this->prepararFirma($firma));
        $this->registrarFirma($hoja);

        return $hoja;
    }

    /** Valida los datos del firmante y guarda las imágenes de firma; devuelve las columnas a actualizar. */
    private function prepararFirma(array $firma): array
    {
        if (empty($firma['receptor_nombre'])) {
            throw ValidationException::withMessages(['receptor_nombre' => 'Indique el nombre de quien firma por la entidad.']);
        }
        if (empty($firma['firma_receptor'])) {
            throw ValidationException::withMessages(['firma_receptor' => 'Falta la firma del responsable de la entidad.']);
        }

        return array_filter([
            'receptor_nombre' => $firma['receptor_nombre'],
            'receptor_dni' => $firma['receptor_dni'] ?? null,
            'receptor_cargo' => $firma['receptor_cargo'] ?? null,
            'conforme' => (bool) ($firma['conforme'] ?? false),
            'firma_receptor' => FirmaService::guardar($firma['firma_receptor'], 'firma_receptor'),
            'firma_tecnico' => ! empty($firma['firma_tecnico']) ? FirmaService::guardar($firma['firma_tecnico'], 'firma_tecnico') : null,
            'estado' => EstadoHoja::Firmada,
            'fecha_firma' => now(),
        ], fn ($v) => $v !== null);
    }

    private function registrarFirma(HojaServicio $hoja): void
    {
        $detalle = "Hoja {$hoja->numero} firmada por {$hoja->receptor_nombre}".($hoja->conforme ? ' (con conformidad)' : ' (con observaciones)');

        if ($hoja->incidencia) {
            $this->incidencias->historial($hoja->incidencia, 'hoja_servicio', null, null, $detalle);
        }

        Auditor::log('firmar', 'hojas_servicio', $detalle, $hoja);

        $supervisores = User::activos()->permission('hojas.ver')->get()->reject(fn ($u) => $u->id === auth()->id());
        Notification::send($supervisores, new HojaServicioFirmada($hoja));
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
     * La hoja es el registro único de la visita: en una incidencia asignada o en proceso,
     * crea la atención técnica (diagnóstico, trabajo y resultado) y avanza su estado.
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
        $inicio = \Illuminate\Support\Carbon::parse("$dia {$hoja->hora_llegada}");
        $fin = $hoja->hora_salida ? \Illuminate\Support\Carbon::parse("$dia {$hoja->hora_salida}") : null;

        return [
            'fecha_inicio' => $inicio->isFuture() ? now() : $inicio,
            'fecha_fin' => $fin && $fin->isFuture() ? now() : $fin,
            'diagnostico' => $hoja->diagnostico ?: $hoja->trabajo_realizado,
            'actividades' => $hoja->trabajo_realizado,
            'observaciones' => $hoja->observaciones,
            'resultado' => $hoja->resultado,
            'latitud' => $hoja->latitud,
            'longitud' => $hoja->longitud,
        ];
    }

    /** Si el material es del almacén, su nombre y unidad salen del artículo. */
    private function completarMaterial(array $fila): array
    {
        $articulo = ! empty($fila['articulo_id']) ? \App\Models\AlmacenArticulo::find($fila['articulo_id']) : null;
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
            throw ValidationException::withMessages(['estado' => 'La hoja de servicio ya fue firmada o anulada y no puede modificarse.']);
        }
    }
}
