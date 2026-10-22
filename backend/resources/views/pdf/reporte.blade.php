<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    @include('pdf._estilos')
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => mb_strtoupper($titulo),
    'datos' => ['Registros' => $filas->count(), 'Generado' => $generado->format('d/m/Y h:i A'), 'Por' => $usuario],
    'pie' => $titulo,
])

<p class="filtros"><strong>Filtros:</strong> {{ $filtros ? implode(' · ', $filtros) : 'Ninguno (todos los registros)' }}</p>

@include('reportes.tablas.'.$tipo)

@if ($filas->isEmpty())
    <p style="text-align: center; color: #6b7280; margin-top: 20px;">No hay registros para los filtros seleccionados.</p>
@endif
</body>
</html>
