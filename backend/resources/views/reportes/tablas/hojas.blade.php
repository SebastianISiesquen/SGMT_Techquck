<table>
    @isset($excel)
        <tr><td colspan="11">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y h:i A') }}</td></tr>
    @endisset
    <thead>
    <tr><th>N°</th><th>Fecha</th><th>Horario</th><th>Tipo de servicio</th><th>Cliente</th><th>Incidencia</th><th>Técnico</th><th>Equipos</th><th>Recibió</th><th>Estado</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $h)
        <tr>
            <td>{{ $h->numero }}</td>
            <td>{{ $h->fecha->format('d/m/Y') }}</td>
            <td>{{ \App\Support\Hora::doce($h->hora_llegada) }}–{{ $h->hora_salida ? \App\Support\Hora::doce($h->hora_salida) : '' }}</td>
            <td>{{ $h->tipo_servicio->label() }}</td>
            <td>{{ $h->proyecto->entidad->nombre }}</td>
            <td>{{ $h->incidencia?->codigo }}</td>
            <td>{{ $h->tecnico?->name }}</td>
            <td class="num">{{ $h->equipos_count }}</td>
            <td>{{ $h->receptor_nombre }}{{ $h->tieneActa() ? ' (con acta)' : '' }}</td>
            <td>{{ $h->estado->label() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
