<table>
    @isset($excel)
        <tr><td colspan="12">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y h:i A') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Código</th><th>Tipo</th><th>Proyecto / servicio</th><th>Cliente</th><th>Orden de compra</th><th>Duración</th><th>Inicio</th><th>Vencimiento</th><th>Días rest.</th><th>Estado</th><th>Incidencias</th><th>Observaciones</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $g)
        <tr>
            <td>{{ $g->codigo }}</td>
            <td>{{ $g->tipo }}</td>
            <td>{{ $g->proyecto->nombre }}</td>
            <td>{{ $g->proyecto->entidad->nombre }}</td>
            <td>{{ $g->orden_compra ?: $g->proyecto->orden_compra }}</td>
            <td>{{ $g->duracionTexto() }}</td>
            <td>{{ $g->fecha_inicio->format('d/m/Y') }}</td>
            <td>{{ $g->fecha_vencimiento->format('d/m/Y') }}</td>
            <td class="num">{{ max(0, $g->diasRestantes()) }}</td>
            <td class="estado-{{ $g->estado->value }}">{{ $g->estado->label() }}</td>
            <td class="num">{{ $g->incidencias_count }}</td>
            <td>{{ $g->observaciones }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
