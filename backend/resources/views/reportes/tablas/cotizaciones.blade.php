<table>
    @isset($excel)
        <tr><td colspan="9">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Asunto</th><th>Referencia</th><th>Moneda</th><th>Total</th><th>Estado</th><th>Elaborada por</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $c)
        <tr>
            <td>{{ $c->numero }}</td>
            <td>{{ $c->fecha->format('d/m/Y') }}</td>
            <td>{{ $c->entidad->nombre }}</td>
            <td>{{ $c->asunto }}</td>
            <td>{{ collect([$c->incidencia?->codigo, $c->equipo?->codigo])->filter()->implode(' · ') }}</td>
            <td>{{ $c->moneda }}</td>
            <td class="num">{{ number_format($c->total, 2, '.', isset($excel) ? '' : ',') }}</td>
            <td>{{ $c->estadoLabel() }}</td>
            <td>{{ $c->autor?->name }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
