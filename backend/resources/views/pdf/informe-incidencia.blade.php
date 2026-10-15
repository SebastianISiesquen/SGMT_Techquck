<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe {{ $incidencia->codigo }}</title>
    @include('pdf._estilos')
    <style> body { font-size: 9.5px; } </style>
</head>
<body>
<header>
    <div class="empresa">{{ $empresa['empresa_nombre'] }}</div>
    <div class="sub">RUC {{ $empresa['empresa_ruc'] }} · {{ $empresa['empresa_direccion'] }}</div>
    <div class="titulo">
        <h1>Informe técnico de incidencia</h1>
        <div class="sub">{{ $incidencia->codigo }} · {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</header>
<footer>
    <span>{{ $incidencia->codigo }} · Estado: {{ $incidencia->estado->label() }}</span>
    <span class="pag" style="float: right;"></span>
</footer>

<h2>1. Datos generales</h2>
<table class="kv">
    <tr><td class="k">Cliente</td><td>{{ $incidencia->proyecto->entidad->nombre }}{{ $incidencia->proyecto->entidad->ruc ? ' · RUC '.$incidencia->proyecto->entidad->ruc : '' }}</td></tr>
    @if ($incidencia->proyecto->clienteFinal)
        <tr><td class="k">Cliente final</td><td>{{ $incidencia->proyecto->clienteFinal->nombre }} (lugar del servicio)</td></tr>
    @endif
    <tr><td class="k">Proyecto / servicio</td><td>{{ $incidencia->proyecto->codigo }} · {{ $incidencia->proyecto->nombre }}{{ $incidencia->proyecto->orden_compra ? ' · OC '.$incidencia->proyecto->orden_compra : '' }}</td></tr>
    <tr><td class="k">Cobertura</td><td>{{ $incidencia->coberturaLabel() }}</td></tr>
    @if ($incidencia->equipo)
        <tr><td class="k">Equipo</td><td>{{ $incidencia->equipo->codigo }} · {{ $incidencia->equipo->nombre() }}{{ $incidencia->equipo->ubicacion ? ' · '.$incidencia->equipo->ubicacion : '' }}</td></tr>
    @endif
    <tr><td class="k">Garantía</td><td>{{ $incidencia->garantia ? $incidencia->garantia->etiqueta().' (vence '.$incidencia->garantia->fecha_vencimiento->format('d/m/Y').' · '.$incidencia->garantia->estado->label().')' : '—' }}</td></tr>
    <tr><td class="k">Incidencia</td><td><strong>{{ $incidencia->titulo }}</strong></td></tr>
    <tr><td class="k">Prioridad / Estado</td><td>{{ $incidencia->prioridad->label() }} / {{ $incidencia->estado->label() }}</td></tr>
    <tr><td class="k">Reportado por</td><td>{{ $incidencia->reportado_por ?? '—' }} {{ $incidencia->canal ? '('.$incidencia->canal.')' : '' }} · {{ $incidencia->fecha_reporte->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="k">Ubicación / equipo</td><td>{{ $incidencia->ubicacion_detalle ?? '—' }}</td></tr>
    <tr><td class="k">Técnico responsable</td><td>{{ $incidencia->tecnico?->name ?? '—' }}</td></tr>
    @if ($incidencia->fecha_resolucion)
        <tr><td class="k">Fecha de resolución</td><td>{{ $incidencia->fecha_resolucion->format('d/m/Y H:i') }}</td></tr>
    @endif
</table>
<div class="caja pre" style="margin-top: 6px;">{{ $incidencia->descripcion }}</div>

<h2>2. Atenciones técnicas</h2>
@forelse ($incidencia->atenciones->sortBy('fecha_inicio') as $a)
    <div class="caja">
        <table class="kv">
            <tr><td class="k">Fecha</td><td>{{ $a->fecha_inicio->format('d/m/Y H:i') }}{{ $a->fecha_fin ? ' – '.$a->fecha_fin->format('d/m/Y H:i') : '' }} · {{ $a->tecnico?->name }}</td></tr>
            <tr><td class="k">Diagnóstico</td><td class="pre">{{ $a->diagnostico }}</td></tr>
            <tr><td class="k">Actividades</td><td class="pre">{{ $a->actividades }}</td></tr>
            @if ($a->observaciones)<tr><td class="k">Observaciones</td><td class="pre">{{ $a->observaciones }}</td></tr>@endif
            <tr><td class="k">Resultado</td><td><strong>{{ $a->resultado->label() }}</strong></td></tr>
        </table>
    </div>
@empty
    <p>No se registraron atenciones.</p>
@endforelse

@if ($fotos->isNotEmpty())
    <h2>3. Registro fotográfico</h2>
    <table>
        @foreach ($fotos->chunk(3) as $fila)
            <tr>
                @foreach ($fila as $f)
                    <td style="width: 33%; border: none; text-align: center; padding: 4px;">
                        <img src="{{ $f['src'] }}" style="width: 100%; max-height: 170px; object-fit: cover; border: 1px solid #e5e7eb;">
                        <div style="font-size: 7.5px; color: #6b7280;">{{ $f['nombre'] }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

<h2>{{ $fotos->isNotEmpty() ? '4' : '3' }}. Trazabilidad</h2>
<table>
    <thead><tr><th>Fecha</th><th>Acción</th><th>Estado</th><th>Detalle</th><th>Usuario</th></tr></thead>
    <tbody>
    @foreach ($incidencia->historial->sortBy('id') as $h)
        <tr>
            <td>{{ $h->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $h->accionLabel() }}</td>
            <td>{{ $h->estado_nuevo?->label() }}</td>
            <td>{{ $h->detalle }}</td>
            <td>{{ $h->usuario?->name }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top: 50px;">
    <tr>
        <td style="border: none; width: 45%; text-align: center; border-top: 1px solid #1f2937; padding-top: 4px;">Técnico responsable<br>{{ $incidencia->tecnico?->name }}</td>
        <td style="border: none; width: 10%;"></td>
        <td style="border: none; width: 45%; text-align: center; border-top: 1px solid #1f2937; padding-top: 4px;">Conformidad del cliente<br>{{ $incidencia->proyecto->lugarDeServicio()->nombre }}</td>
    </tr>
</table>
</body>
</html>
