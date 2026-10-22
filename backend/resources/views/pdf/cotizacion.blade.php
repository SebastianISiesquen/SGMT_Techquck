@php($empresa = \App\Support\Ajustes::all())
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $c->numero }}</title>
    @include('pdf._estilos')
    <style>
        body { font-size: 10px; }
        .totales td { border: none; padding: 3px 4px; }
        .totales .total td { font-weight: bold; font-size: 11px; border-top: 1px solid #0b1d3a; }
    </style>
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => 'COTIZACIÓN',
    'numero' => $c->numero,
    'datos' => ['Fecha' => $c->fecha->format('d/m/Y'), 'Válida hasta' => $c->venceEl()->format('d/m/Y'), 'Moneda' => $c->moneda],
    'pie' => "Cotización {$c->numero}",
])

<table class="kv">
    <tr><td class="k">Cliente</td><td><strong>{{ $c->entidad->nombre }}</strong>{{ $c->entidad->ruc ? ' · RUC '.$c->entidad->ruc : '' }}</td></tr>
    @if ($c->entidad->contacto_nombre)<tr><td class="k">Atención</td><td>{{ $c->entidad->contacto_nombre }}{{ $c->entidad->contacto_cargo ? ' – '.$c->entidad->contacto_cargo : '' }}</td></tr>@endif
    <tr><td class="k">Asunto</td><td>{{ $c->asunto }}</td></tr>
    @if ($c->equipo)<tr><td class="k">Equipo</td><td>{{ $c->equipo->codigo }} · {{ $c->equipo->nombre() }}{{ $c->equipo->ubicacion ? ' · '.$c->equipo->ubicacion : '' }}</td></tr>@endif
    @if ($c->incidencia)<tr><td class="k">Referencia</td><td>Incidencia {{ $c->incidencia->codigo }}</td></tr>@endif
</table>

@if ($c->diagnostico)
    <h2>Diagnóstico</h2>
    <div class="caja pre">{{ $c->diagnostico }}</div>
@endif

<h2>Propuesta económica</h2>
<table>
    <thead><tr><th style="width: 5%;">#</th><th>Descripción</th><th style="width: 9%; text-align: right;">Cant.</th><th style="width: 10%;">Unidad</th><th style="width: 14%; text-align: right;">P. unitario</th><th style="width: 14%; text-align: right;">Importe</th></tr></thead>
    <tbody>
    @foreach ($c->items as $n => $i)
        <tr><td>{{ $n + 1 }}</td><td>{{ $i->descripcion }}</td><td class="num">{{ rtrim(rtrim(number_format($i->cantidad, 2), '0'), '.') }}</td><td>{{ $i->unidad }}</td>
            <td class="num">{{ $c->monto($i->precio_unitario) }}</td><td class="num">{{ $c->monto($i->importe()) }}</td></tr>
    @endforeach
    </tbody>
</table>
<table class="totales" style="width: 40%; margin-left: 60%; margin-top: 6px;">
    <tr><td>Subtotal</td><td class="num">{{ $c->monto($c->subtotal) }}</td></tr>
    <tr><td>IGV</td><td class="num">{{ $c->monto($c->igv) }}</td></tr>
    <tr class="total"><td>Total</td><td class="num">{{ $c->monto($c->total) }}</td></tr>
</table>

@if ($c->condiciones)
    <h2>Condiciones comerciales</h2>
    <p class="pre">{{ $c->condiciones }}</p>
@endif
<p>Validez de la oferta: {{ $c->validez_dias }} días (hasta el {{ $c->venceEl()->format('d/m/Y') }}).</p>

<p style="margin-top: 30px;">Atentamente,<br><strong>{{ $c->autor?->name }}</strong><br>{{ $empresa['empresa_nombre'] }}</p>
</body>
</html>
