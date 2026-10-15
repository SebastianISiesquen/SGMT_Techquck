@props(['title' => null])
@php
    $user = auth()->user();
    $noLeidas = $user->unreadNotifications()->latest()->limit(8)->get();
    $totalNoLeidas = $user->unreadNotifications()->count();
    $empresa = \App\Support\Ajustes::get('empresa_nombre');

    // Un solo módulo de incidencias: el supervisor ve todas; el técnico, las suyas.
    $rutaIncidencias = $user->can('incidencias.ver') ? 'incidencias.index' : 'mis-incidencias.index';

    $menu = [
        ['Principal', [
            ['dashboard', 'Dashboard', 'chart', 'dashboard.ver', 'dashboard'],
        ]],
        ['Operación', [
            [$rutaIncidencias, 'Incidencias', 'alert', ['incidencias.ver', 'atenciones.registrar'], ['incidencias.*', 'mis-incidencias.*']],
            ['mantenimientos.index', 'Mantenimientos', 'calendar', 'mantenimientos.ver', ['mantenimientos.*', 'planes.*']],
            ['hojas.index', 'Hojas de servicio', 'clipboard', ['hojas.ver', 'hojas.registrar'], 'hojas.*'],
            ['almacen.index', 'Almacén', 'box', 'almacen.ver', 'almacen.*'],
        ]],
        ['Clientes y contratos', [
            ['entidades.index', 'Clientes', 'building', 'entidades.ver', 'entidades.*'],
            ['proyectos.index', 'Proyectos y servicios', 'folder', 'proyectos.ver', 'proyectos.*'],
            ['garantias.index', 'Garantías', 'shield', 'garantias.ver', 'garantias.*'],
            ['equipos.index', 'Equipos', 'monitor', 'equipos.ver', 'equipos.*'],
        ]],
        ['Comercial', [
            ['cotizaciones.index', 'Cotizaciones', 'receipt', 'cotizaciones.ver', 'cotizaciones.*'],
            ['reportes.index', 'Reportes', 'file', 'reportes.ver', 'reportes.*'],
        ]],
        ['Administración', [
            ['usuarios.index', 'Usuarios', 'users', 'usuarios.gestionar', 'usuarios.*'],
            ['roles.index', 'Roles y permisos', 'key', 'usuarios.gestionar', 'roles.*'],
            ['checklists.edit', 'Checklists de mantenimiento', 'check-circle', 'equipos.gestionar', 'checklists.*'],
            ['auditoria.index', 'Auditoría', 'history', 'auditoria.ver', 'auditoria.*'],
            ['configuracion.edit', 'Configuración', 'settings', 'configuracion.gestionar', 'configuracion.*'],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}SGGP {{ $empresa }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231f63c0' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><path d='M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z'/><path d='m9 12 2 2 4-4'/></svg>">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ menu: false }">
<div class="min-h-screen lg:flex">

    {{-- Barra lateral --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="menu = false"></div>
    {{-- Oculto desde el HTML en celulares (no depende de que cargue el JavaScript); Alpine solo lo abre. --}}
    <aside :class="menu && 'translate-x-0!'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-brand-950 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-white"><x-icon name="shield" class="size-5" /></div>
            <div class="leading-tight">
                <p class="text-sm font-bold text-white">SGGP</p>
                <p class="text-[11px] text-slate-400">Gestión de Garantías</p>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @foreach ($menu as [$seccion, $items])
                @php($visibles = collect($items)->filter(fn ($i) => collect((array) $i[3])->contains(fn ($p) => $user->can($p))))
                @if ($visibles->isNotEmpty())
                    <p class="nav-section">{{ $seccion }}</p>
                    @foreach ($visibles as [$ruta, $texto, $icono, $permiso, $patron])
                        <a href="{{ route($ruta) }}" class="nav-link {{ request()->routeIs($patron) ? 'active' : '' }}">
                            <x-icon :name="$icono" class="size-[18px]" /> {{ $texto }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-4 text-[11px] text-slate-500">{{ $empresa }}</div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Barra superior --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button class="btn btn-ghost -ml-2 p-2 lg:hidden" @click="menu = true" aria-label="Abrir menú"><x-icon name="menu" /></button>
            <div class="flex-1"></div>

            {{-- Notificaciones --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button class="btn btn-ghost relative p-2" @click="open = !open" aria-label="Notificaciones">
                    <x-icon name="bell" />
                    @if ($totalNoLeidas)
                        <span class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $totalNoLeidas > 9 ? '9+' : $totalNoLeidas }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                        <p class="text-sm font-semibold">Notificaciones</p>
                        @if ($totalNoLeidas)
                            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">@csrf<button class="text-xs text-brand-600 hover:underline">Marcar todas leídas</button></form>
                        @endif
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        @forelse ($noLeidas as $n)
                            <a href="{{ route('notificaciones.abrir', $n->id) }}" class="block border-b border-slate-50 px-4 py-3 hover:bg-slate-50">
                                <p class="text-sm font-medium text-slate-800">{{ $n->data['titulo'] ?? '' }}</p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $n->data['mensaje'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</p>
                            </a>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-slate-500">No tiene notificaciones nuevas.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('notificaciones.index') }}" class="block bg-slate-50 py-2 text-center text-xs font-medium text-brand-600 hover:bg-slate-100">Ver todas</a>
                </div>
            </div>

            {{-- Usuario --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button class="flex items-center gap-2.5 rounded-lg p-1 pr-2 hover:bg-slate-100" @click="open = !open">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $user->iniciales() }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-medium text-slate-800">{{ $user->name }}</span>
                        <span class="block text-[11px] text-slate-500">{{ $user->rolNombre() }}</span>
                    </span>
                </button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    <a href="{{ route('perfil') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="user" class="size-4" /> Mi perfil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if ($errors->has('general') || $errors->has('estado'))
                <div class="mb-5 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <x-icon name="alert" class="size-5 shrink-0" /> {{ $errors->first('general') ?: $errors->first('estado') }}
                </div>
            @elseif ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="flex items-center gap-2 font-medium"><x-icon name="alert" class="size-5" /> Revise los datos del formulario:</p>
                    <ul class="mt-1 list-inside list-disc pl-7 text-xs">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
<x-dialogos />
@stack('scripts')
</body>
</html>
