<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SGGP</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-brand-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, #2a78d6 0, transparent 45%), radial-gradient(circle at 80% 70%, #1baf7a 0, transparent 40%);"></div>
        <div class="relative flex items-center gap-3 text-white">
            <div class="flex size-11 items-center justify-center rounded-xl bg-brand-600"><x-icon name="shield" class="size-6" /></div>
            <div>
                <p class="text-lg font-bold">SGGP</p>
                <p class="text-xs text-slate-400">{{ \App\Support\Ajustes::get('empresa_nombre') }}</p>
            </div>
        </div>
        <div class="relative max-w-md">
            <h1 class="text-3xl font-semibold leading-tight text-white">Sistema Web para la Gestión y Seguimiento de Garantías</h1>
            <p class="mt-4 text-slate-300">Proyectos y servicios para clientes públicos y privados: garantías, equipos, incidencias, atención técnica y evidencias en una sola plataforma.</p>
            <ul class="mt-8 space-y-3 text-sm text-slate-300">
                <li class="flex items-center gap-3"><x-icon name="shield" class="size-5 text-emerald-400" /> Control de vigencia y alertas de vencimiento</li>
                <li class="flex items-center gap-3"><x-icon name="wrench" class="size-5 text-emerald-400" /> Trazabilidad de incidencias hasta su cierre</li>
                <li class="flex items-center gap-3"><x-icon name="chart" class="size-5 text-emerald-400" /> Dashboard y reportes en PDF / Excel</li>
            </ul>
        </div>
        <p class="relative text-xs text-slate-500">Transparencia · Control · Eficiencia</p>
    </div>

    <div class="flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="shield" class="size-5" /></div>
                <p class="text-lg font-bold text-slate-900">SGGP</p>
            </div>
            <h2 class="text-2xl font-semibold text-slate-900">Iniciar sesión</h2>
            <p class="mt-1 text-sm text-slate-500">Ingrese con las credenciales asignadas por el Administrador.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <x-input name="email" type="email" label="Correo electrónico" required autofocus autocomplete="username" placeholder="usuario@techquk.com.pe" />
                <div x-data="{ ver: false }">
                    <label for="f_password" class="label">Contraseña <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input id="f_password" name="password" :type="ver ? 'text' : 'password'" type="password" required autocomplete="current-password" class="input pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" @click="ver = !ver" aria-label="Mostrar contraseña"><x-icon name="eye" class="size-4" /></button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600"> Mantener sesión iniciada
                </label>
                <button class="btn btn-primary w-full py-2.5">Ingresar</button>
            </form>

            <p class="mt-10 flex items-center justify-center gap-2 text-xs text-slate-400">
                <x-icon name="smartphone" class="size-4" /> Técnicos: también disponible en la app móvil
            </p>
        </div>
    </div>
</div>
</body>
</html>
