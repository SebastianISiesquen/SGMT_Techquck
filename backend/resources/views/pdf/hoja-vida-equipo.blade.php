@php($empresa = \App\Support\Ajustes::all())
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de vida {{ $equipo->codigo }}</title>
    @include('pdf._estilos')
    <style>
        body { font-size: 9.5px; }
        .qr { position: absolute; right: 0; top: 0; text-align: center; font-size: 8px; color: #6b7280; }
        .evento td { border-bottom: 1px solid #e5e7eb; }
        .tipo { font-weight: bold; font-size: 8px; text-transform: uppercase; }
        .t-falla { color: #b91c1c; } .t-diagnostico { color: #b45309; } .t-mantenimiento { color: #047857; }
        .t-correctivo, .t-instalacion { color: #1d4ed8; } .t-cambio_componente { color: #6d28d9; }
    </style>
</head>
<body>
<header>
    <div class="empresa">{{ $empresa['empresa_nombre'] }}</div>
    <div class="sub">RUC {{ $empresa['empresa_ruc'] }} · {{ $empresa['empresa_direccion'] }}</div>
    <div class="titulo">
        <h1>Hoja de vida del equipo</h1>
        <div class="sub">{{ $equipo->codigo }} · Generado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</header>
<footer>
    <span>{{ $equipo->codigo }} · {{ $equipo->nombre() }}</span>
    <span class="pag" style="float: right;"></span>
</footer>

<div style="position: relative;">
    <div class="qr">
        <img src="{{ \App\Support\Qr::dataUri($equipo->contenidoQr(), 160) }}" style="width: 78px; height: 78px;"><br>{{ $equipo->codigo }}
    </div>
    <h2 style="margin-top: 0; margin-right: 95px;">1. Datos del equipo</h2>
    <table class="kv" style="width: 82%;">
        <tr><td class="k">Equipo</td><td><strong>{{ trim($equipo->categoria.' '.$equipo->marca.' '.$equipo->modelo) }}</strong></td></tr>
        <tr><td class="k">N° de serie</td><td>{{ $equipo->serie ?? 'No legible / no registrada' }}</td></tr>
        @if ($equipo->descripcion)<tr><td class="k">Descripción</td><td>{{ $equipo->descripcion }}</td></tr>@endif
        <tr><td class="k">Cliente</td><td>{{ $equipo->entidad->nombre }}</td></tr>
        @if ($equipo->proyecto)
            <tr><td class="k">Proyecto / servicio</td><td>{{ $equipo->proyecto->codigo }} · {{ $equipo->proyecto->nombre }}
                @if ($equipo->proyecto->clienteFinal) (contratado por {{ $equipo->proyecto->entidad->nombre }}) @endif</td></tr>
        @endif
        <tr><td class="k">Ubicación</td><td>{{ collect([$equipo->area, $equipo->ubicacion])->filter()->implode(' · ') ?: '—' }}</td></tr>
        <tr><td class="k">Responsable / usuario</td><td>{{ $equipo->responsable ?? '—' }}</td></tr>
        <tr><td class="k">Instalación / entrega</td><td>{{ $equipo->fecha_instalacion?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="k">Estado actual</td><td><strong>{{ $equipo->estadoLabel() }}</strong></td></tr>
        <tr><td class="k">Garantía</td><td>{{ $garantia ? $garantia->codigo.' · '.$garantia->tipo.' · vigente hasta el '.$garantia->fecha_vencimiento->format('d/m/Y') : 'Sin garantía vigente' }}</td></tr>
    </table>
</div>

@php($ev = $equipo->eventos)
<h2>2. Resumen</h2>
<table class="kv">
    <tr>
        <td class="k">Fallas reportadas</td><td>{{ $ev->where('tipo', 'falla')->count() }}</td>
        <td class="k">Mantenimientos preventivos</td><td>{{ $ev->where('tipo', 'mantenimiento')->count() }}</td>
    </tr>
    <tr>
        <td class="k">Cambios de componentes</td><td>{{ $ev->where('tipo', 'cambio_componente')->count() }}</td>
        <td class="k">Último mantenimiento</td><td>{{ $ev->where('tipo', 'mantenimiento')->first()?->fecha->format('d/m/Y') ?? '—' }}</td>
    </tr>
</table>

<h2>3. Historial ({{ $ev->count() }} registros, del más reciente al más antiguo)</h2>
<table class="evento">
    <thead><tr><th style="width: 13%;">Fecha</th><th style="width: 16%;">Tipo</th><th>Detalle</th><th style="width: 15%;">Responsable</th></tr></thead>
    <tbody>
    @forelse ($ev as $e)
        <tr>
            <td>{{ $e->fecha->format('d/m/Y H:i') }}</td>
            <td><span class="tipo t-{{ $e->tipo }}">{{ $e->tipoLabel() }}</span></td>
            <td><strong>{{ $e->titulo }}</strong>
                @if ($e->componente)<br>Componente: {{ $e->componente }}@endif
                @if ($e->detalle)<br><span class="pre">{{ $e->detalle }}</span>@endif
                @if ($e->incidencia || $e->hojaServicio)<br><span style="color: #6b7280;">{{ collect([$e->incidencia?->codigo, $e->hojaServicio?->numero])->filter()->implode(' · ') }}</span>@endif
                @if ($e->estado_equipo)<br><span style="color: #6b7280;">Estado: {{ \App\Models\Equipo::ESTADOS[$e->estado_equipo] ?? $e->estado_equipo }}</span>@endif
            </td>
            <td>{{ $e->usuario?->name ?? 'Sistema' }}</td>
        </tr>
    @empty
        <tr><td colspan="4">Sin registros.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
