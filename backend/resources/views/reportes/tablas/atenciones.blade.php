<table>
    @isset($excel)
        <tr><td colspan="9">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y h:i A') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Fecha</th><th>Incidencia</th><th>Cliente</th><th>Técnico</th><th>Diagnóstico</th><th>Actividades</th><th>Resultado</th><th>Evidencias</th><th>Origen</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $a)
        <tr>
            <td>{{ $a->fecha_inicio->format('d/m/Y h:i A') }}</td>
            <td>{{ $a->incidencia->codigo }} · {{ $a->incidencia->titulo }}</td>
            <td>{{ $a->incidencia->proyecto->entidad->nombre }}</td>
            <td>{{ $a->tecnico?->name }}</td>
            <td>{{ $a->diagnostico }}</td>
            <td>{{ $a->actividades }}</td>
            <td>{{ $a->resultado->label() }}</td>
            <td class="num">{{ $a->archivos_count }}</td>
            <td>{{ $a->origen === 'movil' ? 'App móvil' : 'Web' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
