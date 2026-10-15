<?php

namespace App\Services;

use App\Models\Atencion;
use App\Models\Equipo;
use App\Models\EquipoEvento;
use App\Models\HojaServicio;
use App\Models\HojaServicioEquipo;
use App\Models\Incidencia;
use App\Models\Proyecto;
use Illuminate\Support\Str;

/**
 * Hoja de vida de los equipos. Cada falla, diagnóstico, mantenimiento o cambio de
 * componente queda registrado en el equipo, para que cualquier técnico conozca sus
 * antecedentes aunque lo haya atendido otra persona.
 */
class EquipoService
{
    /** Tipo de servicio de la hoja → tipo de evento en el historial. */
    private const EVENTO_POR_SERVICIO = [
        'preventivo' => 'mantenimiento',
        'correctivo' => 'correctivo',
        'instalacion' => 'instalacion',
        'inspeccion' => 'inspeccion',
    ];

    /** Estado final que se anota en la hoja → estado del equipo en el inventario. */
    private const ESTADO_POR_RESULTADO = [
        'operativo' => 'operativo',
        'observado' => 'observado',
        'inoperativo' => 'inoperativo',
    ];

    /**
     * Registra los equipos instalados en un proyecto. Cada fila puede traer una cantidad
     * (p. ej. 120 cámaras del mismo modelo): se crea un equipo por unidad, cada uno con su código.
     *
     * @param  array<int, array{categoria: string, marca?: ?string, modelo?: ?string, serie?: ?string, ubicacion?: ?string, area?: ?string, cantidad?: int}>  $filas
     */
    public static function registrarEnProyecto(Proyecto $proyecto, array $filas): int
    {
        $cliente = $proyecto->lugarDeServicio();
        $garantiaId = $proyecto->garantias()->activas()->where('tipo', 'like', '%equipo%')->value('id')
            ?? $proyecto->garantias()->activas()->orderByDesc('fecha_vencimiento')->value('id');
        $fecha = $proyecto->esServicio() ? null : $proyecto->fecha_entrega;
        $creados = 0;

        foreach ($filas as $fila) {
            if (! filled($fila['categoria'] ?? null)) {
                continue;
            }
            $cantidad = filled($fila['serie'] ?? null) ? 1 : max(1, (int) ($fila['cantidad'] ?? 1));

            for ($i = 0; $i < $cantidad; $i++) {
                $equipo = Equipo::create([
                    'entidad_id' => $cliente->id,
                    'proyecto_id' => $proyecto->id,
                    'garantia_id' => $garantiaId,
                    'categoria' => $fila['categoria'],
                    'marca' => $fila['marca'] ?? null,
                    'modelo' => $fila['modelo'] ?? null,
                    'serie' => $fila['serie'] ?? null,
                    'ubicacion' => $fila['ubicacion'] ?? null,
                    'area' => $fila['area'] ?? null,
                    'fecha_instalacion' => $fecha,
                ]);
                self::registrarEvento($equipo, $fecha ? 'instalacion' : 'registro',
                    $fecha ? "Instalado en el proyecto {$proyecto->codigo}" : "Registrado en el servicio {$proyecto->codigo}",
                    ['fecha' => $fecha ?? now()]);
                $creados++;
            }
        }

        return $creados;
    }

    public static function registrarEvento(Equipo $equipo, string $tipo, string $titulo, array $extra = []): EquipoEvento
    {
        $evento = $equipo->eventos()->create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'fecha' => $extra['fecha'] ?? now(),
            'user_id' => $extra['user_id'] ?? auth()->id(),
            ...collect($extra)->only(['detalle', 'componente', 'estado_equipo', 'incidencia_id', 'hoja_servicio_id'])->all(),
        ]);

        if (! empty($extra['estado_equipo'])) {
            $equipo->update(['estado' => $extra['estado_equipo']]);
        }

        return $evento;
    }

    public static function alRegistrarIncidencia(Incidencia $incidencia): void
    {
        if (! $incidencia->equipo) {
            return;
        }

        self::registrarEvento($incidencia->equipo, 'falla', "{$incidencia->titulo} ({$incidencia->codigo})", [
            'detalle' => $incidencia->descripcion,
            'incidencia_id' => $incidencia->id,
            'fecha' => $incidencia->fecha_reporte,
            'estado_equipo' => $incidencia->equipo->estado === 'de_baja' ? null : 'con_falla',
        ]);
    }

    public static function alRegistrarAtencion(Atencion $atencion): void
    {
        $incidencia = $atencion->incidencia;
        if (! $incidencia->equipo) {
            return;
        }

        $resuelto = in_array($atencion->resultado->value ?? $atencion->resultado, ['resuelto', 'no_procede'], true);

        self::registrarEvento($incidencia->equipo, 'diagnostico', Str::limit($atencion->diagnostico, 150)." ({$incidencia->codigo})", [
            'detalle' => "Diagnóstico: {$atencion->diagnostico}\nActividades: {$atencion->actividades}"
                .($atencion->observaciones ? "\nObservaciones: {$atencion->observaciones}" : ''),
            'incidencia_id' => $incidencia->id,
            'fecha' => $atencion->fecha_fin ?? $atencion->fecha_inicio,
            'estado_equipo' => $resuelto ? 'operativo' : 'en_reparacion',
        ]);
    }

    /**
     * Vincula cada fila de equipos de la hoja con el inventario del cliente (por id o por serie);
     * si la serie no existe aún, registra el equipo. Luego anota la intervención en su historial.
     */
    public static function alGuardarHoja(HojaServicio $hoja): void
    {
        $hoja->loadMissing(['proyecto', 'incidencia']);
        EquipoEvento::where('hoja_servicio_id', $hoja->id)->delete();

        $cliente = $hoja->proyecto->lugarDeServicio();
        $tipo = self::EVENTO_POR_SERVICIO[$hoja->tipo_servicio->value] ?? 'mantenimiento';

        foreach ($hoja->equipos as $fila) {
            $equipo = self::identificar($fila, $cliente->id, $hoja);
            if (! $equipo) {
                continue;
            }

            if ($fila->equipo_id !== $equipo->id) {
                $fila->update(['equipo_id' => $equipo->id]);
            }

            $componente = $fila->accion === 'cambio_componente' ? ($fila->observacion ?: 'Componente') : null;
            $hallazgos = collect($fila->hallazgos())
                ->map(fn ($p) => '• '.$p['item'].': '.($p['resultado'] === 'falla' ? 'FALLA' : 'observado').(! empty($p['nota']) ? " ({$p['nota']})" : ''));

            self::registrarEvento($equipo, $componente ? 'cambio_componente' : $tipo,
                "{$hoja->tipo_servicio->label()} · {$fila->accionLabel()} (hoja {$hoja->numero})", [
                    'detalle' => collect([
                        $hoja->diagnostico ? 'Diagnóstico: '.$hoja->diagnostico : null,
                        $fila->observacion,
                        'Trabajo: '.$hoja->trabajo_realizado,
                        $hallazgos->isNotEmpty() ? "Checklist con observaciones:\n".$hallazgos->implode("\n") : null,
                        $hoja->materiales->isNotEmpty() ? 'Materiales: '.$hoja->materiales->map(fn ($m) => rtrim(rtrim(number_format($m->cantidad, 2, '.', ''), '0'), '.')." {$m->unidad} {$m->descripcion}")->implode(', ') : null,
                    ])->filter()->implode("\n"),
                    'componente' => $componente,
                    // Un punto del checklist en falla u observado deja el equipo «con observaciones» aunque se marque operativo
                    'estado_equipo' => $fila->accion === 'retirado' ? 'de_baja'
                        : ($hallazgos->isNotEmpty() && $fila->estado_final === 'operativo' ? 'observado' : (self::ESTADO_POR_RESULTADO[$fila->estado_final] ?? null)),
                    'hoja_servicio_id' => $hoja->id,
                    'incidencia_id' => $hoja->incidencia_id,
                    'fecha' => $hoja->fecha->copy()->setTimeFromTimeString($hoja->hora_salida ?? $hoja->hora_llegada),
                    'user_id' => $hoja->tecnico_id,
                ]);
        }
    }

    private static function identificar(HojaServicioEquipo $fila, int $clienteId, HojaServicio $hoja): ?Equipo
    {
        if ($fila->equipo_id) {
            return Equipo::find($fila->equipo_id);
        }

        if (! filled($fila->serie)) {
            return null;
        }

        $existente = Equipo::where('entidad_id', $clienteId)->where('serie', $fila->serie)->first();
        if ($existente) {
            return $existente;
        }

        $equipo = Equipo::create([
            'entidad_id' => $clienteId,
            'proyecto_id' => $hoja->proyecto_id,
            'garantia_id' => $hoja->garantia_id,
            'categoria' => $fila->equipo,
            'marca' => $fila->marca,
            'modelo' => $fila->modelo,
            'serie' => $fila->serie,
            'ubicacion' => $fila->ubicacion,
            'fecha_instalacion' => $hoja->tipo_servicio->value === 'instalacion' ? $hoja->fecha : null,
        ]);

        // Sin hoja_servicio_id: este evento se conserva aunque la hoja se edite después.
        self::registrarEvento($equipo, 'registro', "Registrado desde la hoja de servicio {$hoja->numero}", [
            'fecha' => $hoja->fecha,
            'user_id' => $hoja->tecnico_id,
        ]);

        return $equipo;
    }
}
