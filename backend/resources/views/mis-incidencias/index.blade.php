<x-layouts.app title="Mis incidencias">
    <x-page-header title="Mis incidencias" subtitle="Incidencias asignadas para su atención." />

    <div class="mb-5 grid grid-cols-3 gap-3 sm:gap-4">
        <x-stat label="Asignadas" :value="$resumen['asignadas']" icon="alert" tone="brand" />
        <x-stat label="En proceso" :value="$resumen['en_proceso']" icon="wrench" tone="amber" />
        <x-stat label="Resueltas este mes" :value="$resumen['resueltas_mes']" icon="check-circle" tone="green" />
    </div>

    @php($estadoActual = request('estado', 'abiertas'))
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['abiertas' => 'Pendientes', 'resuelta' => 'Resueltas', 'cerrada' => 'Cerradas'] as $k => $v)
            <a href="{{ route('mis-incidencias.index', ['estado' => $k]) }}"
               class="rounded-full px-3 py-1.5 text-sm font-medium {{ $estadoActual === $k ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $v }}</a>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($incidencias as $i)
            <a href="{{ route('mis-incidencias.show', $i) }}" class="card block p-5 transition hover:border-brand-200 hover:shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-mono text-slate-500">{{ $i->codigo }}</p>
                        <p class="mt-0.5 font-semibold text-slate-900">{{ $i->titulo }}</p>
                    </div>
                    <x-badge :color="$i->prioridad->color()">{{ $i->prioridad->label() }}</x-badge>
                </div>
                <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $i->descripcion }}</p>
                <div class="mt-3 space-y-1 text-xs text-slate-500">
                    <p class="flex items-center gap-1.5"><x-icon name="building" class="size-3.5" /> {{ $i->proyecto->entidad->nombre }}</p>
                    @if ($i->ubicacion_detalle)<p class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5" /> {{ $i->ubicacion_detalle }}</p>@endif
                    <p class="flex items-center gap-1.5"><x-icon name="calendar" class="size-3.5" /> Reportada {{ $i->fecha_reporte->format('d/m/Y H:i') }}
                        @if ($i->fecha_limite) · límite {{ $i->fecha_limite->format('d/m/Y H:i') }} @endif</p>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                    @if ($i->estaVencidaSla())<span class="text-xs font-medium text-red-600">Fuera de plazo</span>@endif
                </div>
            </a>
        @empty
            <div class="card md:col-span-2"><x-empty icon="check-circle" text="No tiene incidencias en esta bandeja." /></div>
        @endforelse
    </div>
    @if ($incidencias->hasPages())<div class="mt-4">{{ $incidencias->links() }}</div>@endif
</x-layouts.app>
