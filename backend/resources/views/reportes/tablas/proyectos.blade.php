<table>
    @isset($excel)
        <tr><td colspan="12">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Código</th><th>Proyecto / servicio</th><th>Clase</th><th>Cliente</th><th>Cliente final</th><th>Rubro</th><th>Orden de compra</th><th>Monto (S/)</th><th>Entrega / inicio</th><th>Estado</th><th>Garantías</th><th>Inc. abiertas / total</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $p)
        <tr>
            <td>{{ $p->codigo }}</td>
            <td>{{ $p->nombre }}</td>
            <td>{{ $p->esServicio() ? 'Servicio' : 'Implementación' }}</td>
            <td>{{ $p->entidad->nombre }}</td>
            <td>{{ $p->clienteFinal?->nombre }}</td>
            <td>{{ $p->tipo }}</td>
            <td>{{ $p->orden_compra }}</td>
            <td class="num">{{ $p->monto_contrato ? number_format($p->monto_contrato, 2, '.', isset($excel) ? '' : ',') : '' }}</td>
            <td>{{ $p->fecha_entrega->format('d/m/Y') }}{{ $p->fecha_fin ? ' – '.$p->fecha_fin->format('d/m/Y') : '' }}</td>
            <td>{{ $p->estadoLabel() }}</td>
            <td class="num">{{ $p->garantias_count }}</td>
            <td class="num">{{ $p->abiertas_count }} / {{ $p->incidencias_count }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
