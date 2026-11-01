{{-- Informe técnico por equipo reparado: una sección (página) por equipo de la incidencia. --}}
@php
    $p = $incidencia->proyecto;
    $resultadoColor = ['resuelto' => 'e-verde', 'parcial' => 'e-ambar', 'pendiente_repuesto' => 'e-azul', 'no_procede' => 'e-rojo'];
    $cantidad = fn ($m) => rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe técnico por equipo · {{ $incidencia->codigo }}</title>
    @include('pdf._estilos')
</head>
<body>
@foreach ($informes as $inf)
    @php
        $e = $inf['equipo'];
        $garantia = $e->garantiaVigente() ?? $incidencia->garantia;
        $n = 0;
    @endphp
    <div @class(['salto' => ! $loop->first])>
    @include('pdf._cabecera', [
        'titulo' => 'INFORME TÉCNICO DEL EQUIPO',
        'numero' => $e->codigo,
        'datos' => ['Incidencia' => $incidencia->codigo, 'Reporte' => $incidencia->fecha_reporte->format('d/m/Y'), 'Emitido' => now()->format('d/m/Y')],
        'pie' => "Informe técnico por equipo · {$incidencia->codigo}",
    ])

    <table class="datos">
        <tr><td class="k">Cliente</td><td>{{ $p->lugarDeServicio()->nombre }}</td><td class="k">Proyecto</td><td style="width: 30%;">{{ $p->codigo }} · {{ $p->nombre }}</td></tr>
        <tr><td class="k">Equipo</td><td>{{ $e->codigo }} · {{ $e->nombre() }}</td><td class="k">N° de serie</td><td>{{ $e->serie ?? '—' }}</td></tr>
        <tr><td class="k">Ubicación</td><td>{{ collect([$e->area, $e->ubicacion])->filter()->implode(' · ') ?: '—' }}</td><td class="k">Responsable</td><td>{{ $e->responsable ?? '—' }}</td></tr>
        <tr><td class="k">Garantía</td><td colspan="3">{{ $garantia ? $garantia->etiqueta().' · vence '.$garantia->fecha_vencimiento->format('d/m/Y') : 'Sin garantía vigente' }} · {{ $incidencia->coberturaLabel() }}</td></tr>
        @if ($incidencia->origen)<tr><td class="k">Antecedente</td><td colspan="3">Quedó pendiente en la incidencia {{ $incidencia->origen->codigo }}</td></tr>@endif
    </table>

    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>FALLA REPORTADA</div>
        <div class="seccion-cuerpo"><strong>{{ $incidencia->titulo }}</strong>
            <div class="pre" style="margin-top: 3px;">{{ $incidencia->descripcion }}</div>
            <div class="muted" style="margin-top: 3px;">Reportado por {{ $incidencia->reportado_por ?? '—' }} el {{ $incidencia->fecha_reporte->format('d/m/Y h:i A') }}</div>
        </div>
    </div>

    <div class="seccion">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>DIAGNÓSTICO Y TRABAJO REALIZADO</div>
        @forelse ($inf['visitas'] as $v)
            @php($h = $v->hoja)
            <table class="evitar-corte" style="margin-top: {{ $loop->first ? 0 : 6 }}px;">
                <thead><tr><th colspan="2">Visita {{ $loop->iteration }} · {{ $h->fecha->format('d/m/Y') }} · {{ $h->tecnico?->name }} · Hoja {{ $h->numero }}</th></tr></thead>
                <tbody>
                    <tr><td style="width: 22%;" class="muted">Diagnóstico</td><td class="pre">{{ $v->diagnostico ?: ($h->diagnostico ?? '—') }}</td></tr>
                    <tr><td class="muted">Acción</td><td>{{ $v->accionLabel() }}{{ $v->observacion ? ': '.$v->observacion : '' }}</td></tr>
                    <tr><td class="muted">Trabajo de la visita</td><td class="pre">{{ $h->trabajo_realizado }}</td></tr>
                    @if ($v->hallazgos())
                        <tr><td class="muted">Checklist</td><td>{{ collect($v->hallazgos())->map(fn ($c) => $c['item'].': '.($c['resultado'] === 'falla' ? 'falla' : 'observado').(! empty($c['nota']) ? ' ('.$c['nota'].')' : ''))->implode('; ') }}</td></tr>
                    @endif
                    @if ($h->materiales->isNotEmpty())
                        <tr><td class="muted">Repuestos / materiales de la visita</td><td>{{ $h->materiales->map(fn ($m) => $cantidad($m).' '.$m->unidad.' '.$m->descripcion)->implode('; ') }}</td></tr>
                    @endif
                    <tr><td class="muted">Estado final</td><td>{{ $v->estadoLabel() }}
                        @if ($v->resultado) · <span class="etiqueta {{ $resultadoColor[$v->resultado->value] ?? 'e-gris' }}">{{ $v->resultado->label() }}</span>@endif</td></tr>
                </tbody>
            </table>
        @empty
            @forelse ($inf['atenciones'] as $a)
                <table class="evitar-corte" style="margin-top: {{ $loop->first ? 0 : 6 }}px;">
                    <thead><tr><th colspan="2">Atención {{ $loop->iteration }} · {{ $a->fecha_inicio->format('d/m/Y') }} · {{ $a->tecnico?->name }}</th></tr></thead>
                    <tbody>
                        <tr><td style="width: 22%;" class="muted">Diagnóstico</td><td class="pre">{{ $a->diagnostico }}</td></tr>
                        <tr><td class="muted">Trabajo realizado</td><td class="pre">{{ $a->actividades }}</td></tr>
                        @if ($a->observaciones)<tr><td class="muted">Observaciones</td><td class="pre">{{ $a->observaciones }}</td></tr>@endif
                        <tr><td class="muted">Resultado</td><td><span class="etiqueta {{ $resultadoColor[$a->resultado->value] ?? 'e-gris' }}">{{ $a->resultado->label() }}</span></td></tr>
                    </tbody>
                </table>
            @empty
                <div class="seccion-cuerpo muted">Aún no se registra la visita de este equipo.</div>
            @endforelse
        @endforelse
    </div>

    <div class="seccion evitar-corte">
        <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>RESULTADO</div>
        <div class="seccion-cuerpo">
            @if ($inf['resultado'])
                <span class="etiqueta {{ $resultadoColor[$inf['resultado']->value] ?? 'e-gris' }}">{{ $inf['resultado']->label() }}</span>
            @else
                <span class="etiqueta e-gris">Pendiente de atención</span>
            @endif
            · Estado actual del equipo: <strong>{{ $e->estadoLabel() }}</strong>
        </div>
    </div>

    @if ($inf['fotos']->isNotEmpty())
        <div class="seccion evitar-corte">
            <div class="seccion-titulo"><span class="n">{{ ++$n }}</span>REGISTRO FOTOGRÁFICO</div>
            <div style="height: 4px;"></div>
            @include('pdf._fotos', ['fotos' => $inf['fotos']])
        </div>
    @endif

    <table class="firmas evitar-corte" style="margin-top: 40px;">
        <tr>
            <td><div class="firma-linea"><strong>{{ $inf['visitas']->last()?->hoja->tecnico?->name ?? $incidencia->tecnico?->name }}</strong><br>Técnico responsable</div></td>
            <td><div class="firma-linea"><strong>{{ $p->lugarDeServicio()->nombre }}</strong><br>Conformidad del cliente (firma y sello)</div></td>
        </tr>
    </table>
    </div>
@endforeach
</body>
</html>
