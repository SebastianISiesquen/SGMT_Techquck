<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · SGGP</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4 font-sans antialiased">
    <div class="card w-full max-w-md p-8 text-center">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full {{ $tono }}"><x-icon :name="$icono" class="size-7" /></div>
        <p class="text-sm font-semibold text-slate-500">Error {{ $codigo }}</p>
        <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $titulo }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $mensaje }}</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-secondary">&larr; Volver</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a>
        </div>
    </div>
</body>
</html>
