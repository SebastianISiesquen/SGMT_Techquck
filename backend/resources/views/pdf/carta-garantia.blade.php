@php
    $empresa = \App\Support\Ajustes::all();
    $oc = $g->orden_compra ?: $p->orden_compra;
    $destinatario = $p->entidad;
    \Carbon\Carbon::setLocale('es');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carta de garantía {{ $g->codigo }}</title>
    @include('pdf._estilos')
    <style>
        body { font-size: 10.5px; line-height: 1.55; }
        p { margin: 0 0 9px; text-align: justify; }
        .derecha { text-align: right; }
        .numero { font-size: 12px; font-weight: bold; color: #0b1d3a; text-align: center; margin: 10px 0 14px; letter-spacing: .5px; }
        .datos td { border: 1px solid #e5e7eb; padding: 5px 7px; }
        .datos td.k { background: #f1f5f9; width: 30%; color: #334155; font-weight: bold; }
        .firma { margin-top: 55px; width: 45%; border-top: 1px solid #0b1d3a; text-align: center; padding-top: 4px; }
    </style>
</head>
<body>
<header>
    <div class="empresa">{{ $empresa['empresa_nombre'] }}</div>
    <div class="sub">RUC {{ $empresa['empresa_ruc'] }} · {{ $empresa['empresa_direccion'] }}{{ $empresa['empresa_telefono'] ? ' · '.$empresa['empresa_telefono'] : '' }}{{ $empresa['empresa_email'] ? ' · '.$empresa['empresa_email'] : '' }}</div>
    <div class="titulo">
        <h1>Carta de garantía</h1>
        <div class="sub">N° {{ $g->codigo }}</div>
    </div>
</header>
<footer>
    <span>Carta de garantía {{ $g->codigo }} · {{ $empresa['empresa_nombre'] }}</span>
    <span class="pag" style="float: right;"></span>
</footer>

<p class="derecha">Chiclayo, {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

<p>Señores:<br>
<strong>{{ $destinatario->nombre }}</strong><br>
@if ($destinatario->ruc) RUC {{ $destinatario->ruc }}<br>@endif
@if ($destinatario->direccion){{ $destinatario->direccion }}<br>@endif
@if ($destinatario->contacto_nombre) Atención: {{ $destinatario->contacto_nombre }}{{ $destinatario->contacto_cargo ? ' – '.$destinatario->contacto_cargo : '' }}@endif
</p>

<div class="numero">CARTA DE GARANTÍA N° {{ $g->codigo }}</div>

<p>Por medio de la presente, <strong>{{ $empresa['empresa_nombre'] }}</strong>, con RUC {{ $empresa['empresa_ruc'] }}, otorga la presente garantía
    por el {{ $p->esServicio() ? 'servicio' : 'proyecto' }} <strong>«{{ $p->nombre }}»</strong>@if ($oc), correspondiente a la <strong>orden de compra N° {{ $oc }}</strong>@endif
    @if ($p->clienteFinal), ejecutado para el cliente final <strong>{{ $p->clienteFinal->nombre }}</strong>@endif, en los términos que se detallan a continuación:</p>

<table class="datos">
    <tr><td class="k">Garantía</td><td>{{ $g->tipo }} — {{ $g->descripcion }}</td></tr>
    <tr><td class="k">Proyecto / servicio</td><td>{{ $p->codigo }} · {{ $p->nombre }}</td></tr>
    <tr><td class="k">Cliente</td><td>{{ $p->entidad->nombre }}</td></tr>
    @if ($p->clienteFinal)<tr><td class="k">Cliente final</td><td>{{ $p->clienteFinal->nombre }}</td></tr>@endif
    @if ($oc)<tr><td class="k">Orden de compra</td><td>{{ $oc }}</td></tr>@endif
    @if ($p->numero_contrato)<tr><td class="k">Contrato</td><td>{{ $p->numero_contrato }}</td></tr>@endif
    <tr><td class="k">Vigencia</td><td>{{ $g->duracionTexto() }}: del <strong>{{ $g->fecha_inicio->format('d/m/Y') }}</strong> al <strong>{{ $g->fecha_vencimiento->format('d/m/Y') }}</strong></td></tr>
    @if ($g->monto)<tr><td class="k">Monto garantizado</td><td>S/ {{ number_format($g->monto, 2) }}</td></tr>@endif
    @if ($g->proveedor)<tr><td class="k">Fabricante / proveedor</td><td>{{ $g->proveedor }}{{ $g->numero_documento ? ' · Certificado N° '.$g->numero_documento : '' }}</td></tr>@endif
</table>

@if ($g->cobertura)
    <h2>Cobertura</h2>
    <p class="pre">{{ $g->cobertura }}</p>
@endif

@if ($g->equipos->isNotEmpty())
    <h2>Equipos cubiertos ({{ $g->equipos->count() }})</h2>
    <table>
        <thead><tr><th>Código</th><th>Equipo</th><th>Marca / modelo</th><th>Serie</th><th>Ubicación</th></tr></thead>
        <tbody>
        @foreach ($g->equipos as $e)
            <tr><td>{{ $e->codigo }}</td><td>{{ $e->categoria }}</td><td>{{ trim($e->marca.' '.$e->modelo) }}</td><td>{{ $e->serie }}</td><td>{{ $e->ubicacion }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Condiciones y exclusiones</h2>
<p class="pre">{{ $g->condiciones ?: 'La garantía cubre defectos de fabricación de los equipos y fallas atribuibles a la instalación realizada por la empresa. No cubre daños por mal uso, manipulación por terceros, descargas eléctricas, desastres naturales, vandalismo ni el desgaste normal de consumibles.' }}</p>

<p>Para hacer efectiva la garantía, el cliente deberá comunicar la falla{{ $empresa['empresa_telefono'] ? ' al teléfono '.$empresa['empresa_telefono'] : '' }}{{ $empresa['empresa_email'] ? ' o al correo '.$empresa['empresa_email'] : '' }}, indicando el equipo afectado y, de ser posible, el código del sticker de identificación.</p>

<p>Sin otro particular, quedamos de ustedes.</p>
<p>Atentamente,</p>

<div class="firma">
    {{ $empresa['empresa_representante'] ?: '______________________________' }}<br>
    {{ $empresa['empresa_cargo_representante'] }}<br>
    <strong>{{ $empresa['empresa_nombre'] }}</strong>
</div>
</body>
</html>
