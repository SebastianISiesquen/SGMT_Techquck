<table>
    @isset($excel)
        <tr><td colspan="11">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>N°</th><th>Fecha</th><th>Horario</th><th>Tipo de servicio</th><th>Cliente</th><th>Incidencia</th><th>Técnico</th><th>Equipos</th><th>Firmado por</th><th>Estado</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $h)
        <tr>
            <td>{{ $h->numero }}</td>
            <td>{{ $h->fecha->format('d/m/Y') }}</td>
            <td>{{ substr($h->hora_llegada, 0, 5) }}–{{ $h->hora_salida ? substr($h->hora_salida, 0, 5) : '' }}</td>
            <td>{{ $h->tipo_servicio->label() }}</td>
            <td>{{ $h->proyecto->entidad->nombre }}</td>
            <td>{{ $h->incidencia?->codigo }}</td>
            <td>{{ $h->tecnico?->name }}</td>
            <td class="num">{{ $h->equipos_count }}</td>
            <td>{{ $h->receptor_nombre }}{{ $h->estaFirmada() ? ($h->conforme ? ' (conforme)' : ' (obs.)') : '' }}</td>
            <td>{{ $h->estado->label() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
