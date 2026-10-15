<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    @include('pdf._estilos')
</head>
<body>
<header>
    <div class="empresa">{{ $empresa['empresa_nombre'] }}</div>
    <div class="sub">RUC {{ $empresa['empresa_ruc'] }} · {{ $empresa['empresa_direccion'] }}</div>
    <div class="sub">Sistema de Gestión y Seguimiento de Garantías</div>
    <div class="titulo">
        <h1>{{ $titulo }}</h1>
        <div class="sub">Generado el {{ $generado->format('d/m/Y H:i') }} por {{ $usuario }}</div>
    </div>
</header>
<footer>
    <span>{{ $empresa['empresa_nombre'] }} · {{ $titulo }} · {{ $filas->count() }} registro(s)</span>
    <span class="pag" style="float: right;"></span>
</footer>

<p class="filtros"><strong>Filtros:</strong> {{ $filtros ? implode(' · ', $filtros) : 'Ninguno (todos los registros)' }}</p>

@include('reportes.tablas.'.$tipo)

@if ($filas->isEmpty())
    <p style="text-align: center; color: #6b7280; margin-top: 20px;">No hay registros para los filtros seleccionados.</p>
@endif
</body>
</html>
