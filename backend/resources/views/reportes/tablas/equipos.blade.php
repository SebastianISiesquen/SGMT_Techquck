<table>
    @isset($excel)
        <tr><td colspan="13">{{ $titulo }} — {{ $empresa['empresa_nombre'] }} — {{ $generado->format('d/m/Y H:i') }}</td></tr>
    @endisset
    <thead>
    <tr><th>Código</th><th>Categoría</th><th>Marca</th><th>Modelo</th><th>Serie</th><th>Cliente</th><th>Ubicación</th><th>Responsable</th><th>Instalación</th><th>Garantía hasta</th><th>Fallas</th><th>Último mant.</th><th>Estado</th></tr>
    </thead>
    <tbody>
    @foreach ($filas as $e)
        <tr>
            <td>{{ $e->codigo }}</td>
            <td>{{ $e->categoria }}</td>
            <td>{{ $e->marca }}</td>
            <td>{{ $e->modelo }}</td>
            <td>{{ $e->serie }}</td>
            <td>{{ $e->entidad->nombre }}</td>
            <td>{{ collect([$e->area, $e->ubicacion])->filter()->implode(' · ') }}</td>
            <td>{{ $e->responsable }}</td>
            <td>{{ $e->fecha_instalacion?->format('d/m/Y') }}</td>
            <td>{{ $e->garantia?->fecha_vencimiento->format('d/m/Y') }}</td>
            <td class="num">{{ $e->incidencias_count }}</td>
            <td>{{ $e->ultimo_mantenimiento ? \Illuminate\Support\Carbon::parse($e->ultimo_mantenimiento)->format('d/m/Y') : '' }}</td>
            <td>{{ $e->estadoLabel() }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
