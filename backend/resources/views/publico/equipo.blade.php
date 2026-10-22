@php
    $colores = ['red' => 'bg-red-100 text-red-600', 'amber' => 'bg-amber-100 text-amber-700', 'green' => 'bg-emerald-100 text-emerald-700',
                'blue' => 'bg-blue-100 text-blue-700', 'violet' => 'bg-violet-100 text-violet-700', 'slate' => 'bg-slate-100 text-slate-600'];
    $fallas = $equipo->eventos->where('tipo', 'falla')->count();
    $ultimoMant = $equipo->eventos->firstWhere('tipo', 'mantenimiento');
    $contacto = collect([$empresa['empresa_telefono'], $empresa['empresa_email']])->filter();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $equipo->codigo }} · {{ $equipo->categoria }} · {{ $empresa['empresa_nombre'] }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<header class="bg-brand-950 text-white">
    <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600"><x-icon name="shield" class="size-5" /></div>
        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold">{{ $empresa['empresa_nombre'] }}</p>
            <p class="text-xs text-slate-300">Ficha del equipo · historial de servicio</p>
        </div>
        <a href="{{ route('equipos.qr', $equipo->codigo) }}" class="shrink-0 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium hover:bg-white/20">Personal: ingresar</a>
    </div>
</header>

<main class="mx-auto max-w-3xl space-y-4 px-4 py-5">
    {{-- Identificación --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-mono text-sm font-bold text-brand-600">{{ $equipo->codigo }}</p>
                <h1 class="mt-1 text-xl font-bold text-slate-900">{{ trim($equipo->categoria.' '.$equipo->marca.' '.$equipo->modelo) }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $equipo->serie ? 'N° de serie '.$equipo->serie : 'Serie no legible' }}</p>
            </div>
            <x-badge :color="$equipo->estadoColor()" class="px-3 py-1 text-sm">{{ $equipo->estadoLabel() }}</x-badge>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Cliente</dt><dd class="font-medium">{{ $equipo->entidad->nombre }}</dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Ubicación</dt><dd>{{ collect([$equipo->area, $equipo->ubicacion])->filter()->implode(' · ') ?: '—' }}</dd></div>
            @if ($equipo->responsable)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Responsable</dt><dd>{{ $equipo->responsable }}</dd></div>@endif
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Instalado / entregado</dt><dd>{{ $equipo->fecha_instalacion?->format('d/m/Y') ?? '—' }}</dd></div>
            @if ($equipo->proyecto)<div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Proyecto / servicio</dt><dd>{{ $equipo->proyecto->nombre }}</dd></div>@endif
        </dl>
    </section>

    {{-- Resumen --}}
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs text-slate-500">Garantía</p>
            @if ($garantia)
                <p class="mt-1 text-sm font-bold text-emerald-700">Vigente</p>
                <p class="text-xs text-slate-500">hasta {{ $garantia->fecha_vencimiento->format('d/m/Y') }}</p>
            @else
                <p class="mt-1 text-sm font-bold text-slate-500">Sin garantía vigente</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs text-slate-500">Último mantenimiento</p>
            <p class="mt-1 text-sm font-bold">{{ $ultimoMant?->fecha->format('d/m/Y') ?? 'Sin registro' }}</p>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs text-slate-500">Próximo mantenimiento</p>
            <p class="mt-1 text-sm font-bold">{{ $proximoMantenimiento?->fecha_programada->format('d/m/Y') ?? 'No programado' }}</p>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs text-slate-500">Fallas registradas</p>
            <p class="mt-1 text-sm font-bold {{ $fallas ? 'text-red-600' : '' }}">{{ $fallas }}</p>
        </div>
    </section>

    @if ($contacto->isNotEmpty())
        <section class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm">
            <x-icon name="alert" class="size-5 text-brand-600" />
            <p class="min-w-0 flex-1 text-brand-900">¿El equipo presenta una falla? Comuníquese con soporte e indique el código <strong>{{ $equipo->codigo }}</strong>.</p>
            @if ($empresa['empresa_telefono'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $empresa['empresa_telefono']) }}" class="rounded-lg bg-brand-600 px-3 py-1.5 font-medium text-white">Llamar</a>@endif
            @if ($empresa['empresa_email'])<a href="mailto:{{ $empresa['empresa_email'] }}?subject={{ rawurlencode('Falla en equipo '.$equipo->codigo) }}" class="rounded-lg bg-white px-3 py-1.5 font-medium text-brand-700 ring-1 ring-brand-200">Escribir</a>@endif
        </section>
    @endif

    {{-- Visitas técnicas (hojas de servicio) --}}
    @if ($visitas->isNotEmpty())
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-100 px-5 py-3 font-semibold">Visitas técnicas ({{ $visitas->count() }})</h2>
            @foreach ($visitas as $h)
                <div class="border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium">{{ $h->fecha->format('d/m/Y') }} · {{ $h->tipo_servicio->label() }}</p>
                        @if ($h->resultado)<x-badge :color="\App\Enums\ResultadoAtencion::from($h->resultado)->color()" :dot="false">{{ $h->resultadoLabel() }}</x-badge>@endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($h->trabajo_realizado, 220) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Hoja {{ $h->numero }} · {{ $h->tecnico?->name }}</p>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Historial completo --}}
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-5 py-3 font-semibold">Historial del equipo ({{ $equipo->eventos->count() }})</h2>
        <div class="px-5 py-4">
            @forelse ($equipo->eventos as $ev)
                <div class="relative flex gap-3 pb-5 last:pb-0">
                    @unless ($loop->last)<span class="absolute left-4 top-9 -ml-px h-full w-0.5 bg-slate-200"></span>@endunless
                    <div class="relative flex size-8 shrink-0 items-center justify-center rounded-full {{ $colores[$ev->tipoColor()] ?? $colores['slate'] }}">
                        <x-icon :name="$ev->tipoIcono()" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-500">{{ $ev->fecha->format('d/m/Y H:i') }} · {{ $ev->tipoLabel() }}</p>
                        <p class="text-sm font-medium text-slate-800">{{ $ev->titulo }}</p>
                        @if ($ev->componente)<p class="text-xs text-violet-700">Componente: {{ $ev->componente }}</p>@endif
                        @if ($ev->detalle)<p class="mt-0.5 whitespace-pre-line text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($ev->detalle, 400) }}</p>@endif
                        <p class="mt-0.5 text-xs text-slate-400">{{ collect([$ev->usuario?->name, $ev->incidencia?->codigo, $ev->hojaServicio?->numero])->filter()->implode(' · ') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aún no hay registros en el historial de este equipo.</p>
            @endforelse
        </div>
    </section>

    {{-- Incidencias --}}
    @if ($equipo->incidencias->isNotEmpty())
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-100 px-5 py-3 font-semibold">Incidencias reportadas ({{ $equipo->incidencias->count() }})</h2>
            @foreach ($equipo->incidencias as $i)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $i->titulo }}</p>
                        <p class="text-xs text-slate-500">{{ $i->codigo }} · {{ $i->fecha_reporte->format('d/m/Y') }}{{ $i->tecnico ? ' · '.$i->tecnico->name : '' }}</p>
                    </div>
                    <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                </div>
            @endforeach
        </section>
    @endif

    <p class="pb-6 text-center text-xs text-slate-400">Información generada por el sistema de gestión de garantías de {{ $empresa['empresa_nombre'] }} · {{ now()->format('d/m/Y H:i') }}</p>
</main>
</body>
</html>
