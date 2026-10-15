<table>
    @isset($excel)
        <tr><td colspan="9">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Fecha programada</th><th>Visita</th><th>Plan</th><th>Proyecto / servicio</th><th>Cliente (lugar)</th><th>Técnico</th><th>Estado</th><th>Ejecutado</th><th>Hoja de servicio</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $m)
        <tr>
            <td>{{ $m->fecha_programada->format('d/m/Y') }}</td>
            <td>{{ $m->numero }} de {{ $m->plan->total_visitas }}</td>
            <td>{{ $m->plan->nombre }}</td>
            <td>{{ $m->plan->proyecto->codigo }} · {{ $m->plan->proyecto->nombre }}</td>
            <td>{{ $m->plan->proyecto->lugarDeServicio()->nombre }}</td>
            <td>{{ $m->tecnico?->name }}</td>
            <td>{{ $m->estadoLabel() }}</td>
            <td>{{ $m->fecha_ejecucion?->format('d/m/Y') }}</td>
            <td>{{ $m->hojaServicio?->numero }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
