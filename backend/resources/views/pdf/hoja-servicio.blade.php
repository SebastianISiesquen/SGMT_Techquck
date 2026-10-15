<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $hoja->numero }}</title>
    @include('pdf._estilos')
    <style>
        body { font-size: 9px; }
        .cuadro td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        .cuadro td.k { background: #f1f5f9; color: #334155; font-weight: bold; width: 18%; }
        .marca { display: inline-block; width: 9px; height: 9px; border: 1px solid #334155; margin-right: 3px; text-align: center; line-height: 9px; font-size: 8px; }
        .firmas tr td { background: none !important; }
        .sello { position: fixed; top: 120px; right: 10px; font-size: 40px; color: rgba(220, 38, 38, .18); transform: rotate(-20deg); }
    </style>
</head>
<body>
<header>
    <div class="empresa">{{ $empresa['empresa_nombre'] }}</div>
    <div class="sub">RUC {{ $empresa['empresa_ruc'] }} · {{ $empresa['empresa_direccion'] }}</div>
    <div class="titulo">
        <h1>HOJA DE SERVICIO EN CAMPO</h1>
        <div class="sub" style="font-size: 11px; color: #0b1d3a;"><strong>N° {{ $hoja->numero }}</strong></div>
    </div>
</header>
<footer>
    <span>{{ $hoja->numero }} · {{ $hoja->estado->label() }}{{ $hoja->fecha_firma ? ' el '.$hoja->fecha_firma->format('d/m/Y H:i') : '' }}</span>
    <span class="pag" style="float: right;"></span>
</footer>

@if ($hoja->estado->value === 'anulada')<div class="sello">ANULADA</div>@endif

<table class="cuadro">
    <tr><td class="k">Cliente</td><td colspan="3">{{ $hoja->proyecto->entidad->nombre }}{{ $hoja->proyecto->orden_compra ? ' · OC '.$hoja->proyecto->orden_compra : '' }}</td></tr>
    @if ($hoja->proyecto->clienteFinal)
        <tr><td class="k">Cliente final</td><td colspan="3">{{ $hoja->proyecto->clienteFinal->nombre }} (lugar del servicio)</td></tr>
    @endif
    <tr><td class="k">Proyecto / servicio</td><td colspan="3">{{ $hoja->proyecto->codigo }} · {{ $hoja->proyecto->nombre }}{{ $hoja->mantenimiento ? ' · '.$hoja->mantenimiento->etiqueta() : '' }}</td></tr>
    <tr>
        <td class="k">Garantía</td><td>{{ $hoja->garantia?->etiqueta() ?? '—' }}</td>
        <td class="k">Incidencia</td><td>{{ $hoja->incidencia?->codigo ?? '—' }}</td>
    </tr>
    <tr>
        <td class="k">Fecha</td><td>{{ $hoja->fecha->format('d/m/Y') }}</td>
        <td class="k">Horario</td><td>Llegada {{ substr($hoja->hora_llegada, 0, 5) }} · Salida {{ $hoja->hora_salida ? substr($hoja->hora_salida, 0, 5) : '—' }}{{ $hoja->duracion() ? ' ('.$hoja->duracion().')' : '' }}</td>
    </tr>
    <tr>
        <td class="k">Tipo de servicio</td>
        <td colspan="3">
            @foreach (\App\Enums\TipoServicio::cases() as $t)
                <span style="margin-right: 10px;"><span class="marca">{{ $hoja->tipo_servicio === $t ? 'X' : '' }}</span>{{ $t->label() }}</span>
            @endforeach
        </td>
    </tr>
</table>

<h2>Equipos intervenidos</h2>
<table>
    <thead><tr><th>Código</th><th>Equipo</th><th>Marca</th><th>Modelo</th><th>N° serie</th><th>Ubicación</th><th>Acción</th><th>Estado final</th></tr></thead>
    <tbody>
    @forelse ($hoja->equipos as $i => $e)
        <tr>
            <td>{{ $e->inventario?->codigo ?? $i + 1 }}</td><td>{{ $e->equipo }}</td><td>{{ $e->marca }}</td><td>{{ $e->modelo }}</td><td>{{ $e->serie }}</td>
            <td>{{ $e->ubicacion }}</td><td>{{ $e->accionLabel() }}</td><td>{{ $e->estadoLabel() }}{{ $e->observacion ? ' — '.$e->observacion : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" style="color: #6b7280;">No se registraron equipos.</td></tr>
    @endforelse
    </tbody>
</table>

@php($conChecklist = $hoja->equipos->filter(fn ($e) => ! empty($e->checklist)))
@if ($conChecklist->isNotEmpty())
    <h2>Checklist de mantenimiento</h2>
    @foreach ($conChecklist as $e)
        <p style="margin: 4px 0 2px;"><strong>{{ $e->inventario?->codigo ?? '' }} {{ $e->equipo }}</strong>{{ $e->serie ? ' · S/N '.$e->serie : '' }}</p>
        <table>
            <tbody>
            @foreach ($e->checklist as $p)
                <tr><td style="width: 55%;">{{ $p['item'] }}</td><td style="width: 15%;"><strong>{{ \App\Models\Checklist::RESULTADOS[$p['resultado']] ?? $p['resultado'] }}</strong></td><td>{{ $p['nota'] ?? '' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
@endif

@if ($hoja->diagnostico)
    <h2>Diagnóstico</h2>
    <div class="caja pre">{{ $hoja->diagnostico }}</div>
@endif

<h2>Trabajo realizado</h2>
<div class="caja pre">{{ $hoja->trabajo_realizado }}</div>
@if ($hoja->resultado)<p><strong>Resultado:</strong> {{ $hoja->resultadoLabel() }}</p>@endif

@if ($hoja->materiales->isNotEmpty())
    <h2>Materiales y repuestos utilizados</h2>
    <table>
        <thead><tr><th style="width: 12%;">Cantidad</th><th style="width: 12%;">Unidad</th><th>Descripción</th></tr></thead>
        <tbody>
        @foreach ($hoja->materiales as $m)
            <tr><td class="num">{{ rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.') }}</td><td>{{ $m->unidad }}</td><td>{{ $m->descripcion }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($hoja->observaciones || $hoja->recomendaciones)
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 50%; border: none; padding: 0 6px 0 0; vertical-align: top;">
                <h2 style="margin-top: 0;">Observaciones</h2><div class="caja pre">{{ $hoja->observaciones ?: '—' }}</div>
            </td>
            <td style="width: 50%; border: none; padding: 0 0 0 6px; vertical-align: top;">
                <h2 style="margin-top: 0;">Recomendaciones</h2><div class="caja pre">{{ $hoja->recomendaciones ?: '—' }}</div>
            </td>
        </tr>
    </table>
@endif

@if ($fotos->isNotEmpty())
    <h2>Registro fotográfico</h2>
    <table>
        @foreach ($fotos->chunk(3) as $fila)
            <tr>
                @foreach ($fila as $f)
                    <td style="width: 33%; border: none; text-align: center; padding: 3px;">
                        <img src="{{ $f['src'] }}" style="width: 100%; max-height: 150px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 7.5px; color: #6b7280;">{{ $f['nombre'] }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

<h2>Conformidad</h2>
<p style="margin: 0 0 6px;">
    <span class="marca">{{ $hoja->estaFirmada() && $hoja->conforme ? 'X' : '' }}</span> El cliente da su conformidad al servicio realizado.
    &nbsp;&nbsp;
    <span class="marca">{{ $hoja->estaFirmada() && ! $hoja->conforme ? 'X' : '' }}</span> Recibe con observaciones.
</p>

<table class="firmas" style="margin-top: 8px; page-break-inside: avoid;">
    <tr>
        <td style="width: 46%; border: none; text-align: center; vertical-align: bottom; height: 90px;">
            @if ($firmaTecnico)<img src="{{ $firmaTecnico }}" style="height: 70px;">@endif
        </td>
        <td style="width: 8%; border: none;"></td>
        <td style="width: 46%; border: none; text-align: center; vertical-align: bottom; height: 90px;">
            @if ($firmaReceptor)<img src="{{ $firmaReceptor }}" style="height: 70px;">@endif
        </td>
    </tr>
    <tr>
        <td style="border: none; border-top: 1px solid #1f2937; text-align: center; padding-top: 4px;">
            <strong>{{ $hoja->tecnico?->name }}</strong><br>Técnico · {{ $empresa['empresa_nombre'] }}
        </td>
        <td style="border: none;"></td>
        <td style="border: none; border-top: 1px solid #1f2937; text-align: center; padding-top: 4px;">
            <strong>{{ $hoja->receptor_nombre ?? '________________________' }}</strong><br>
            {{ $hoja->receptor_cargo ?? 'Responsable del cliente' }}{{ $hoja->receptor_dni ? ' · DNI '.$hoja->receptor_dni : '' }}
        </td>
    </tr>
</table>
</body>
</html>
