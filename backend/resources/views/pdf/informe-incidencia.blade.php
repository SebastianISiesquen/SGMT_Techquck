@php
    $p = $incidencia->proyecto;
    $resultadoColor = ['resuelto' => 'e-verde', 'parcial' => 'e-ambar', 'pendiente_repuesto' => 'e-azul', 'no_procede' => 'e-rojo'];
    $hojas = $incidencia->hojasServicio->where('estado', '!=', \App\Enums\EstadoHoja::Anulada)->sortBy([['fecha', 'asc'], ['id', 'asc']])->values();
    $atencionesSueltas = $incidencia->atenciones->whereNotIn('id', $hojas->pluck('atencion_id')->filter()->all())->sortBy('fecha_inicio');
    $n = 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe {{ $incidencia->codigo }}</title>
    @include('pdf._estilos')
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => 'INFORME TÉCNICO',
    'numero' => $incidencia->codigo,
    'datos' => ['Estado' => $incidencia->estado->label(), 'Reporte' => $incidencia->fecha_reporte->format('d/m/Y'), 'Emitido' => now()->format('d/m/Y')],
    'pie' => "Informe técnico {$incidencia->codigo}",
])

<table class="datos">
    <tr><td class="k">Cliente</td><td>{{ $p->entidad->nombre }}</td><td class="k">RUC</td><td style="width: 18%;">{{ $p->entidad->ruc ?? '—' }}</td></tr>
    @if ($p->clienteFinal)<tr><td class="k">Cliente final</td><td colspan="3">{{ $p->clienteFinal->nombre }} <span class="muted">(lugar del servicio)</span></td></tr>@endif
    <tr><td class="k">Proyecto / servicio</td><td>{{ $p->codigo }} · {{ $p->nombre }}</td><td class="k">Orden de compra</td><td>{{ $p->orden_compra ?? '—' }}</td></tr>
    <tr><td class="k">Garantía</td><td>{{ $incidencia->garantia ? $incidencia->garantia->etiqueta().' · vence '.$incidencia->garantia->fecha_vencimiento->format('d/m/Y') : '—' }}</td>
        <td class="k">Cobertura</td><td>{{ $incidencia->coberturaLabel() }}</td></tr>
    @if ($incidencia->equipo)
        <tr><td class="k">Equipo</td><td colspan="3">{{ $incidencia->equipo->codigo }} · {{ $incidencia->equipo->nombre() }}{{ $incidencia->equipo->ubicacion ? ' · '.$incidencia->equipo->ubicacion : '' }}</td></tr>
    @endif
    <tr><td class="k">Reportado por</td><td>{{ $incidencia->reportado_por ?? '—' }}{{ $incidencia->canal ? ' ('.$incidencia->canal.')' : '' }} · {{ $incidencia->fecha_reporte->format('d/m/Y h:i A') }}</td>
        <td class="k">Prioridad</td><td>{{ $incidencia->prioridad->label() }}</td></tr>
    <tr><td class="k">Técnico responsable</td><td>{{ $incidencia->tecnico?->name ?? '—' }}</td>
        <td class="k">Resolución</td><td>{{ $incidencia->fecha_resolucion?->format('d/m/Y h:i A') ?? '—' }}</td></tr>
</table>

<div class="seccion evitar-corte">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>FALLA REPORTADA</div>
    <div class="seccion-cuerpo"><strong>{{ $incidencia->titulo }}</strong><div class="pre" style="margin-top: 3px;">{{ $incidencia->descripcion }}</div></div>
</div>

<div class="seccion">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>VISITAS Y ATENCIÓN TÉCNICA</div>
    @forelse ($hojas as $h)
        <table class="evitar-corte" style="margin-top: {{ $loop->first ? 0 : 6 }}px;">
            <thead><tr><th colspan="2">Visita {{ $loop->iteration }} · {{ $h->fecha->format('d/m/Y') }} · {{ \App\Support\Hora::doce($h->hora_llegada) }}–{{ $h->hora_salida ? \App\Support\Hora::doce($h->hora_salida) : '…' }} · {{ $h->tecnico?->name }} · Hoja {{ $h->numero }}</th></tr></thead>
            <tbody>
                @if ($h->diagnostico)<tr><td style="width: 20%;" class="muted">Diagnóstico</td><td class="pre">{{ $h->diagnostico }}</td></tr>@endif
                <tr><td style="width: 20%;" class="muted">Trabajo realizado</td><td class="pre">{{ $h->trabajo_realizado }}</td></tr>
                @if ($h->equipos->isNotEmpty())<tr><td class="muted">Equipos</td><td>{{ $h->equipos->map(fn ($e) => trim($e->equipo.' '.$e->marca.' '.$e->modelo).($e->serie ? ' (S/N '.$e->serie.')' : '').': '.$e->accionLabel().', '.mb_strtolower($e->estadoLabel()))->implode('; ') }}</td></tr>@endif
                @if ($h->materiales->isNotEmpty())<tr><td class="muted">Materiales</td><td>{{ $h->materiales->map(fn ($m) => rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.').' '.$m->unidad.' '.$m->descripcion)->implode('; ') }}</td></tr>@endif
                @if ($h->resultado)<tr><td class="muted">Resultado</td><td><span class="etiqueta {{ $resultadoColor[$h->resultado] ?? 'e-gris' }}">{{ $h->resultadoLabel() }}</span>
                    @if ($h->requiereVisita()) · Pendiente: {{ $h->pendientes }} · próxima visita {{ $h->proxima_visita?->format('d/m/Y') }}@endif</td></tr>@endif
                <tr><td class="muted">Conformidad</td><td>{{ $h->tieneActa() ? 'Acta de conformidad firmada recibida'.($h->receptor_nombre ? ' ('.$h->receptor_nombre.')' : '') : 'Pendiente de acta' }}</td></tr>
            </tbody>
        </table>
    @empty
        @if ($atencionesSueltas->isEmpty())<div class="seccion-cuerpo muted">No se registraron visitas.</div>@endif
    @endforelse
    @foreach ($atencionesSueltas as $a)
        <table class="evitar-corte" style="margin-top: 6px;">
            <thead><tr><th colspan="2">Atención · {{ $a->fecha_inicio->format('d/m/Y h:i A') }} · {{ $a->tecnico?->name }}</th></tr></thead>
            <tbody>
                <tr><td style="width: 20%;" class="muted">Diagnóstico</td><td class="pre">{{ $a->diagnostico }}</td></tr>
                <tr><td class="muted">Actividades</td><td class="pre">{{ $a->actividades }}</td></tr>
                <tr><td class="muted">Resultado</td><td><span class="etiqueta {{ $resultadoColor[$a->resultado->value] ?? 'e-gris' }}">{{ $a->resultado->label() }}</span></td></tr>
            </tbody>
        </table>
    @endforeach
</div>

<div class="seccion">
    <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>TRAZABILIDAD</div>
    <table>
        <thead><tr><th style="width: 14%;">Fecha</th><th style="width: 15%;">Acción</th><th style="width: 11%;">Estado</th><th>Detalle</th><th style="width: 16%;">Usuario</th></tr></thead>
        <tbody>
        @foreach ($incidencia->historial->sortBy('id') as $h)
            <tr><td>{{ $h->created_at->format('d/m/Y h:i A') }}</td><td>{{ $h->accionLabel() }}</td><td>{{ $h->estado_nuevo?->label() }}</td><td>{{ $h->detalle }}</td><td>{{ $h->usuario?->name }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>

<table class="firmas evitar-corte" style="margin-top: 46px;">
    <tr>
        <td><div class="firma-linea"><strong>{{ $incidencia->tecnico?->name }}</strong><br>Técnico responsable</div></td>
        <td><div class="firma-linea"><strong>{{ $p->lugarDeServicio()->nombre }}</strong><br>Conformidad del cliente (firma y sello)</div></td>
    </tr>
</table>

@if ($fotos->isNotEmpty())
    <div class="seccion salto">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>REGISTRO FOTOGRÁFICO</div>
        <div style="height: 4px;"></div>
        @include('pdf._fotos', ['fotos' => $fotos])
    </div>
@endif
</body>
</html>
