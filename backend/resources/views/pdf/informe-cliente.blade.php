@php
    $cliente = $p->entidad;
    $estados = \App\Models\Equipo::ESTADOS;
    $cant = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $realizadas = $visitas->where('estado', 'realizado')->count();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe {{ $p->codigo }}</title>
    @include('pdf._estilos')
    <style>
        body { font-size: 9.5px; }
        .kpis td { border: 1px solid #e5e7eb; text-align: center; padding: 8px 4px; width: 25%; }
        .kpis .n { font-size: 16px; font-weight: bold; color: #0b1d3a; }
        .kpis .t { font-size: 8px; color: #6b7280; }
        .equipos td { vertical-align: middle; }
        .muted { color: #6b7280; font-size: 8px; }
    </style>
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => 'INFORME DE SERVICIO',
    'numero' => $p->codigo,
    'datos' => $p->esServicio()
        ? ['Periodo' => $desde->format('d/m/Y').' – '.$hasta->format('d/m/Y'), 'Emitido' => now()->format('d/m/Y')]
        : ['Inicio de ejecución' => $p->fecha_inicio?->format('d/m/Y') ?? '—', 'Entrega / conformidad' => $p->fecha_entrega->format('d/m/Y'), 'Emitido' => now()->format('d/m/Y')],
    'pie' => "Informe de servicio {$p->codigo}",
])

<table class="kv">
    <tr><td class="k">Cliente</td><td><strong>{{ $cliente->nombre }}</strong>{{ $cliente->ruc ? ' · RUC '.$cliente->ruc : '' }}</td></tr>
    @if ($p->clienteFinal)<tr><td class="k">Cliente final</td><td>{{ $p->clienteFinal->nombre }}</td></tr>@endif
    <tr><td class="k">{{ $p->esServicio() ? 'Contrato de servicio' : 'Proyecto' }}</td><td>{{ $p->codigo }} · {{ $p->nombre }}{{ $p->orden_compra ? ' · OC '.$p->orden_compra : '' }}</td></tr>
    @if (! $p->esServicio() && $desde->toDateString() !== ($p->fecha_inicio ?? $p->fecha_entrega)->toDateString())
        <tr><td class="k">Actividad reportada</td><td>{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</td></tr>
    @endif
    @if ($p->esServicio())<tr><td class="k">Vigencia del contrato</td><td>{{ $p->fecha_entrega->format('d/m/Y') }} al {{ $p->fecha_fin?->format('d/m/Y') ?? '—' }}</td></tr>@endif
</table>

<h2>{{ $p->esServicio() ? 'Resumen del periodo' : 'Resumen del proyecto' }}</h2>
<table class="kpis">
    <tr>
        <td><div class="n">{{ $hojas->count() }}</div><div class="t">Servicios en campo</div></td>
        <td><div class="n">{{ $realizadas }}/{{ $visitas->count() }}</div><div class="t">Mantenimientos realizados / programados</div></td>
        <td><div class="n">{{ $incidencias->whereIn('estado.value', ['resuelta', 'cerrada'])->count() }}/{{ $incidencias->count() }}</div><div class="t">Incidencias atendidas / reportadas</div></td>
        <td><div class="n">{{ $equipos->count() - $equiposAtencion->count() }}/{{ $equipos->count() }}</div><div class="t">Equipos operativos / totales</div></td>
    </tr>
</table>

@if ($visitas->isNotEmpty())
    <h2>Mantenimientos programados</h2>
    <table>
        <thead><tr><th>Visita</th><th>Fecha programada</th><th>Ejecutado</th><th>Técnico</th><th>Estado</th><th>Hoja</th></tr></thead>
        <tbody>
        @foreach ($visitas as $v)
            <tr><td>{{ $v->etiqueta() }}</td><td>{{ $v->fecha_programada->format('d/m/Y') }}</td><td>{{ $v->fecha_ejecucion?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $v->tecnico?->name }}</td><td>{{ $v->estadoLabel() }}</td><td>{{ $v->hojaServicio?->numero }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Servicios realizados</h2>
@forelse ($hojas as $h)
    <div class="caja">
        <strong>{{ $h->numero }}</strong> · {{ $h->fecha->format('d/m/Y') }} · {{ $h->tipo_servicio->label() }}{{ $h->mantenimiento ? ' · '.$h->mantenimiento->etiqueta() : '' }}{{ $h->incidencia ? ' · Incidencia '.$h->incidencia->codigo : '' }}
        · Técnico: {{ $h->tecnico?->name }} · {{ $h->estado->label() }}{{ $h->tieneActa() && $h->receptor_nombre ? ' · recibió '.$h->receptor_nombre : '' }}
        @if ($h->diagnostico)<div class="pre" style="margin-top: 3px;"><strong>Diagnóstico:</strong> {{ $h->diagnostico }}</div>@endif
        <div class="pre" style="margin-top: 3px;"><strong>Trabajo:</strong> {{ $h->trabajo_realizado }}</div>
        @if ($h->equipos->isNotEmpty())<div style="margin-top: 3px;"><strong>Equipos:</strong> {{ $h->equipos->map(fn ($e) => $e->equipo.($e->serie ? ' ('.$e->serie.')' : '').' – '.$e->estadoLabel())->implode('; ') }}</div>@endif
        @if ($h->recomendaciones)<div class="pre" style="margin-top: 3px;"><strong>Recomendaciones:</strong> {{ $h->recomendaciones }}</div>@endif
    </div>
@empty
    <p>No se registraron servicios en el periodo.</p>
@endforelse

@if ($incidencias->isNotEmpty())
    <h2>Incidencias</h2>
    <table>
        <thead><tr><th>Código</th><th>Reporte</th><th>Incidencia</th><th>Equipo</th><th>Estado</th><th>Cierre</th></tr></thead>
        <tbody>
        @foreach ($incidencias as $i)
            <tr><td>{{ $i->codigo }}</td><td>{{ $i->fecha_reporte->format('d/m/Y') }}</td><td>{{ $i->titulo }}</td>
                <td>{{ $i->equipo ? $i->equipo->codigo.' · '.$i->equipo->categoria : '—' }}</td><td>{{ $i->estado->label() }}</td><td>{{ $i->fecha_cierre?->format('d/m/Y') }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>{{ $p->esServicio() ? 'Estado de los equipos' : 'Equipos instalados' }} ({{ $equipos->count() }})</h2>
@if ($equipos->isNotEmpty())
    <p>{{ collect($equiposPorEstado)->map(fn ($n, $e) => ($estados[$e] ?? $e).': '.$n)->implode(' · ') }}</p>
    <table class="equipos">
        <thead><tr><th style="width: 4%;">N°</th><th>Equipo</th><th style="width: 16%;">Serie</th><th style="width: 24%;">Ubicación</th><th style="width: 11%;">Estado</th><th style="width: 72px; text-align: center;">FEI</th></tr></thead>
        <tbody>
        @foreach ($equipos as $e)
            <tr>
                <td class="num">{{ $loop->iteration }}</td>
                <td><strong>{{ $e->categoria }}</strong>@if (trim($e->marca.' '.$e->modelo))<br>{{ trim($e->marca.' '.$e->modelo) }}@endif<br><span class="muted">{{ $e->codigo }}</span></td>
                <td>{{ $e->serie ?: '—' }}</td>
                <td>{{ collect([$e->area, $e->ubicacion])->filter()->implode(' · ') ?: '—' }}</td>
                <td>{{ $e->estadoLabel() }}</td>
                <td style="text-align: center; padding: 2px;">
                    @if ($f = $feis[$e->id] ?? null)
                        <img src="{{ $f['src'] }}" style="width: {{ $f['ancho'] }}px; height: {{ $f['alto'] }}px;" alt="FEI">
                    @else
                        <span class="muted">Sin foto</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 3px;">FEI: foto del equipo instalado.</p>
@else
    <p>Sin equipos registrados.</p>
@endif

@if ($materiales->isNotEmpty())
    <h2>Materiales utilizados</h2>
    <table>
        <thead><tr><th style="width: 14%;">Cantidad</th><th style="width: 14%;">Unidad</th><th>Descripción</th></tr></thead>
        <tbody>
        @foreach ($materiales as $m)<tr><td class="num">{{ $cant($m['cantidad']) }}</td><td>{{ $m['unidad'] }}</td><td>{{ $m['descripcion'] }}</td></tr>@endforeach
        </tbody>
    </table>
@endif

<h2>Garantías</h2>
<table>
    <thead><tr><th>Código</th><th>Tipo</th><th>Vigencia</th><th>Estado</th><th class="num">Días restantes</th></tr></thead>
    <tbody>
    @forelse ($garantias as $g)
        <tr><td>{{ $g->codigo }}</td><td>{{ $g->tipo }}</td><td>{{ $g->fecha_inicio->format('d/m/Y') }} – {{ $g->fecha_vencimiento->format('d/m/Y') }}</td>
            <td class="estado-{{ $g->estado->value }}">{{ $g->estado->label() }}</td><td class="num">{{ max(0, $g->diasRestantes()) }}</td></tr>
    @empty
        <tr><td colspan="5">Sin garantías registradas.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
