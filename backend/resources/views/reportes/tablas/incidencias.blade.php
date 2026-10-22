<table>
    @isset($excel)
        <tr><td colspan="12">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y h:i A') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Código</th><th>Título</th><th>Proyecto</th><th>Cliente</th><th>Garantía</th><th>Prioridad</th><th>Reporte</th><th>Técnico</th><th>Estado</th><th>Resolución</th><th>Horas</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $i)
        <tr>
            <td>{{ $i->codigo }}</td>
            <td>{{ $i->titulo }}</td>
            <td>{{ $i->proyecto->nombre }}</td>
            <td>{{ $i->proyecto->entidad->nombre }}</td>
            <td>{{ $i->garantia?->codigo }}</td>
            <td>{{ $i->prioridad->label() }}</td>
            <td>{{ $i->fecha_reporte->format('d/m/Y h:i A') }}</td>
            <td>{{ $i->tecnico?->name }}</td>
            <td>{{ $i->estado->label() }}</td>
            <td>{{ $i->fecha_resolucion?->format('d/m/Y h:i A') }}</td>
            <td class="num">{{ $i->fecha_resolucion ? round($i->fecha_reporte->diffInMinutes($i->fecha_resolucion) / 60, 1) : '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
