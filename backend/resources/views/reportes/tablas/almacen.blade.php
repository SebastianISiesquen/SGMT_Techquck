@php($cant = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
<table>
    @isset($excel)
        <tr><td colspan="10">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Fecha</th><th>Código</th><th>Artículo</th><th>Movimiento</th><th>Cantidad</th><th>Unidad</th><th>Costo (S/)</th><th>Cliente / proyecto</th><th>Hoja</th><th>Usuario</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $m)
        <tr>
            <td>{{ $m->fecha->format('d/m/Y H:i') }}</td>
            <td>{{ $m->articulo->codigo }}</td>
            <td>{{ $m->articulo->nombreCompleto() }}</td>
            <td>{{ $m->tipoLabel() }}</td>
            <td class="num">{{ $cant($m->variacion()) }}</td>
            <td>{{ $m->articulo->unidad }}</td>
            <td class="num">{{ $m->costo_unitario ? number_format(abs($m->variacion()) * $m->costo_unitario, 2, '.', isset($excel) ? '' : ',') : '' }}</td>
            <td>{{ $m->proyecto ? $m->proyecto->entidad->nombre.' · '.$m->proyecto->codigo : '' }}</td>
            <td>{{ $m->hojaServicio?->numero }}</td>
            <td>{{ $m->usuario?->name }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
