@php
    $p = $hoja->proyecto;
    $lugar = $p->lugarDeServicio();
    $inc = $hoja->incidencia;
    $hora = fn ($v) => $v ? \App\Support\Hora::doce($v) : '—';
    $correctivo = in_array($hoja->tipo_servicio->value, ['correctivo', 'otro']) || $inc;
    $marcaVerif = ['ok' => ['✔', 'ok'], 'no' => ['✘', 'mal'], 'na' => ['N/A', 'muted']];
    $claseChk = ['ok' => 'ok', 'observado' => 'obs', 'falla' => 'mal', 'na' => 'muted'];
    $resultadoColor = ['resuelto' => 'e-verde', 'parcial' => 'e-ambar', 'pendiente_repuesto' => 'e-azul', 'no_procede' => 'e-rojo'];
    $n = 0;
    $recomendacionesGenerales = \App\Support\Ajustes::catalogo('recomendaciones_generales');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $hoja->numero }}</title>
    @include('pdf._estilos')
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => 'HOJA DE SERVICIO',
    'numero' => $hoja->numero,
    'datos' => array_filter([
        'Incidencia / OT' => $inc?->codigo ?? ($hoja->mantenimiento ? $hoja->mantenimiento->etiqueta() : '—'),
        'Fecha' => $hoja->fecha->format('d/m/Y'),
        'Llegada / salida' => $hora($hoja->hora_llegada).' – '.$hora($hoja->hora_salida),
        'Tipo de servicio' => $hoja->tipo_servicio->label(),
    ]),
    'pie' => "Hoja de servicio {$hoja->numero} · {$hoja->estado->label()}",
])

@if ($hoja->estado->value === 'anulada')<div class="sello-anulado">ANULADA</div>@endif

{{-- Datos del cliente y del servicio --}}
<table class="datos">
    <tr>
        <td class="k">Cliente</td><td>{{ $p->entidad->nombre }}</td>
        <td class="k">RUC</td><td style="width: 18%;">{{ $p->entidad->ruc ?? '—' }}</td>
    </tr>
    @if ($p->clienteFinal)
        <tr><td class="k">Cliente final</td><td colspan="3">{{ $p->clienteFinal->nombre }} <span class="muted">(lugar donde se prestó el servicio)</span></td></tr>
    @endif
    <tr>
        <td class="k">Dirección / lugar</td><td>{{ $inc?->ubicacion_detalle ?: ($p->ubicacion ?: $lugar->direccion) ?: '—' }}</td>
        <td class="k">Contacto</td><td>{{ $inc?->reportado_por ?: $lugar->contacto_nombre ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Teléfono / correo</td><td>{{ collect([$lugar->contacto_telefono, $lugar->contacto_email])->filter()->implode(' · ') ?: '—' }}</td>
        <td class="k">Técnico</td><td>{{ $hoja->tecnico?->name }}</td>
    </tr>
    <tr>
        <td class="k">Proyecto / servicio</td><td>{{ $p->codigo }} · {{ $p->nombre }}</td>
        <td class="k">Orden de compra</td><td>{{ $p->orden_compra ?? '—' }}</td>
    </tr>
    @if ($hoja->garantia || $inc)
        <tr>
            <td class="k">Garantía</td><td>{{ $hoja->garantia ? $hoja->garantia->etiqueta().' · vence '.$hoja->garantia->fecha_vencimiento->format('d/m/Y') : '—' }}</td>
            <td class="k">Cobertura</td><td>{{ $inc?->coberturaLabel() ?? '—' }}</td>
        </tr>
    @endif
</table>

{{-- Verificaciones previas de seguridad --}}
@if ($hoja->verificaciones)
    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>EVALUACIÓN PRELIMINAR · VERIFICACIONES PREVIAS EN SITIO</div>
        <div class="seccion-cuerpo">
            <table class="sin-borde">
                @foreach (collect($hoja->verificaciones)->chunk((int) ceil(count($hoja->verificaciones) / 2)) as $col)
                    @if ($loop->first)<tr>@endif
                    <td style="width: 50%; vertical-align: top; padding: 0 6px 0 0;">
                        @foreach ($col as $v)
                            @php([$simbolo, $clase] = $marcaVerif[$v['resultado']] ?? ['—', 'muted'])
                            <div style="padding: 1.5px 0;"><span class="{{ $clase }}" style="display: inline-block; width: 22px;">{{ $simbolo }}</span>{{ $v['item'] }}</div>
                        @endforeach
                    </td>
                    @if ($loop->last)</tr>@endif
                @endforeach
            </table>
        </div>
    </div>
@endif

{{-- Motivo del servicio y diagnóstico --}}
@if ($correctivo || $hoja->mantenimiento)
    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>{{ $inc ? 'FALLA REPORTADA Y DIAGNÓSTICO' : 'MOTIVO DEL SERVICIO' }}</div>
        <div class="seccion-cuerpo">
            <table class="kv">
                @if ($inc)
                    <tr><td class="k">Falla reportada</td><td><strong>{{ $inc->titulo }}</strong> · {{ $inc->fecha_reporte->format('d/m/Y h:i A') }}{{ $inc->canal ? ' · '.$inc->canal : '' }}</td></tr>
                    <tr><td class="k">Descripción</td><td class="pre">{{ $inc->descripcion }}</td></tr>
                @endif
                @if ($hoja->mantenimiento)
                    <tr><td class="k">Visita programada</td><td>{{ $hoja->mantenimiento->etiqueta() }} · {{ $hoja->mantenimiento->plan->nombre }}</td></tr>
                    @if ($hoja->mantenimiento->plan->alcance)<tr><td class="k">Alcance</td><td class="pre">{{ $hoja->mantenimiento->plan->alcance }}</td></tr>@endif
                @endif
                @if ($hoja->diagnostico)
                    <tr><td class="k">Diagnóstico técnico</td><td class="pre">{{ $hoja->diagnostico }}</td></tr>
                @endif
            </table>
        </div>
    </div>
@endif

{{-- Información técnica de los equipos --}}
<div class="seccion">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>INFORMACIÓN TÉCNICA DE LOS EQUIPOS INTERVENIDOS</div>
    <table>
        <thead><tr><th style="width: 9%;">Código</th><th>Equipo</th><th>Marca / modelo</th><th style="width: 14%;">N° de serie</th><th>Ubicación</th><th style="width: 12%;">Acción</th><th style="width: 15%;">Estado final</th></tr></thead>
        <tbody>
        @forelse ($hoja->equipos as $i => $e)
            <tr>
                <td>{{ $e->inventario?->codigo ?? '—' }}</td>
                <td><strong>{{ $e->equipo }}</strong></td>
                <td>{{ trim($e->marca.' '.$e->modelo) ?: '—' }}</td>
                <td>{{ $e->serie ?? '—' }}</td>
                <td>{{ $e->ubicacion ?? '—' }}</td>
                <td>{{ $e->accionLabel() }}</td>
                <td><span class="{{ ['operativo' => 'ok', 'observado' => 'obs', 'inoperativo' => 'mal'][$e->estado_final] ?? '' }}">{{ $e->estadoLabel() }}</span>
                    @if ($e->observacion)<br><span class="chico muted">{{ $e->observacion }}</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No se registraron equipos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Ejecución: checklist de mantenimiento por equipo --}}
@php($conChecklist = $hoja->equipos->filter(fn ($e) => ! empty($e->checklist)))
@if ($conChecklist->isNotEmpty())
    <div class="seccion">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>EJECUCIÓN DEL SERVICIO · PUNTOS DE CONTROL</div>
        @foreach ($conChecklist as $e)
            <table class="evitar-corte" style="margin-top: {{ $loop->first ? 0 : 5 }}px;">
                <thead><tr><th colspan="3">{{ $e->inventario?->codigo ? $e->inventario->codigo.' · ' : '' }}{{ $e->equipo }}{{ $e->serie ? ' · S/N '.$e->serie : '' }}</th></tr></thead>
                <tbody>
                @foreach ($e->checklist as $pt)
                    <tr>
                        <td style="width: 58%;">{{ $pt['item'] }}</td>
                        <td style="width: 12%;" class="centro"><span class="{{ $claseChk[$pt['resultado']] ?? '' }}">{{ \App\Models\Checklist::RESULTADOS[$pt['resultado']] ?? $pt['resultado'] }}</span></td>
                        <td class="chico">{{ $pt['nota'] ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
@endif

{{-- Trabajo realizado --}}
<div class="seccion evitar-corte">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>TRABAJO REALIZADO</div>
    <div class="seccion-cuerpo pre">{{ $hoja->trabajo_realizado }}</div>
</div>

{{-- Mediciones --}}
@if ($hoja->mediciones)
    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>MEDICIONES</div>
        <table>
            @foreach (collect($hoja->mediciones)->chunk(3) as $fila)
                <tr>
                    @foreach ($fila as $m)
                        <td style="width: 20%; background: #f1f5f9; font-weight: bold;">{{ $m['parametro'] }}</td>
                        <td style="width: 13.3%;" class="num"><strong>{{ $m['valor'] }}</strong> {{ $m['unidad'] }}</td>
                    @endforeach
                    @for ($k = $fila->count(); $k < 3; $k++)<td></td><td></td>@endfor
                </tr>
            @endforeach
        </table>
    </div>
@endif

{{-- Materiales --}}
@if ($hoja->materiales->isNotEmpty())
    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>MATERIALES Y REPUESTOS UTILIZADOS</div>
        <table>
            <thead><tr><th style="width: 5%;" class="centro">#</th><th>Descripción</th><th style="width: 12%;" class="num">Cantidad</th><th style="width: 12%;">Unidad</th></tr></thead>
            <tbody>
            @foreach ($hoja->materiales as $k => $m)
                <tr><td class="centro">{{ $k + 1 }}</td><td>{{ $m->descripcion }}</td><td class="num">{{ rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.') }}</td><td>{{ $m->unidad }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Resultado, pendientes y recomendaciones --}}
<div class="seccion evitar-corte">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>RESULTADO Y RECOMENDACIONES</div>
    <div class="seccion-cuerpo">
        <table class="kv">
            @if ($hoja->resultado)
                <tr><td class="k">Resultado de la visita</td><td><span class="etiqueta {{ $resultadoColor[$hoja->resultado] ?? 'e-gris' }}">{{ $hoja->resultadoLabel() }}</span></td></tr>
            @endif
            @if ($hoja->requiereVisita())
                <tr><td class="k">Queda pendiente</td><td class="pre">{{ $hoja->pendientes }}</td></tr>
                <tr><td class="k">Próxima visita</td><td><strong>{{ $hoja->proxima_visita?->format('d/m/Y') }}</strong></td></tr>
            @endif
            @if ($hoja->observaciones)<tr><td class="k">Observaciones</td><td class="pre">{{ $hoja->observaciones }}</td></tr>@endif
            @if ($hoja->recomendaciones)<tr><td class="k">Recomendaciones</td><td class="pre">{{ $hoja->recomendaciones }}</td></tr>@endif
        </table>
        @if ($recomendacionesGenerales)
            <div style="margin-top: 5px; padding-top: 4px; border-top: 1px dashed #cbd5e1;" class="chico muted">
                @foreach ($recomendacionesGenerales as $r)<div>• {{ $r }}</div>@endforeach
            </div>
        @endif
    </div>
</div>

{{-- Visitas anteriores de la misma incidencia --}}
@if ($visitasPrevias->isNotEmpty())
    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>VISITAS ANTERIORES DE ESTA INCIDENCIA</div>
        <table>
            <thead><tr><th style="width: 13%;">Hoja</th><th style="width: 10%;">Fecha</th><th>Trabajo realizado</th><th style="width: 22%;">Resultado</th></tr></thead>
            <tbody>
            @foreach ($visitasPrevias as $v)
                <tr><td>{{ $v->numero }}</td><td>{{ $v->fecha->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Str::limit($v->trabajo_realizado, 180) }}</td>
                    <td>{{ $v->resultadoLabel() ?? '—' }}{{ $v->pendientes ? ' · Pendiente: '.\Illuminate\Support\Str::limit($v->pendientes, 60) : '' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Registro fotográfico --}}
@if ($fotos->isNotEmpty())
    <div class="seccion {{ $fotos->count() > 2 ? 'salto' : '' }}">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>REGISTRO FOTOGRÁFICO</div>
        <div style="height: 4px;"></div>
        @include('pdf._fotos', ['fotos' => $fotos])
    </div>
@endif
</body>
</html>
