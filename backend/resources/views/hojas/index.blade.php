<x-layouts.app title="Hojas de servicio">
    <x-page-header title="Hojas de servicio en campo" :subtitle="$verTodas ? 'Servicios realizados en campo, con el acta de conformidad de los clientes.' : 'Sus hojas de servicio registradas.'">
        <x-slot:actions>
            @can('hojas.registrar')
                <a href="{{ route('hojas.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva hoja de servicio</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-input name="buscar" :value="request('buscar')" placeholder="N°, trabajo o quien recibió…" />
            <x-select name="estado" :options="$estados" :value="request('estado')" placeholder="Todos los estados" />
            <x-select name="tipo_servicio" :options="$tipos" :value="request('tipo_servicio')" placeholder="Todos los tipos" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" />
            @if ($verTodas)
                <x-select name="tecnico_id" :options="$tecnicos" :value="request('tecnico_id')" placeholder="Todos los técnicos" />
            @endif
            <x-input name="desde" type="date" :value="request('desde')" title="Desde" />
            <x-input name="hasta" type="date" :value="request('hasta')" title="Hasta" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>N° / Fecha</th><th>Servicio</th><th>Cliente / Proyecto</th><th>Técnico</th><th>Recibió</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($hojas as $h)
                    <tr>
                        <td class="whitespace-nowrap"><a href="{{ route('hojas.show', $h) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $h->numero }}</a>
                            <p class="text-xs text-slate-500">{{ $h->fecha->format('d/m/Y') }} · {{ substr($h->hora_llegada, 0, 5) }}</p></td>
                        <td>{{ $h->tipo_servicio->label() }}
                            @if ($h->incidencia)<p class="text-xs text-slate-500">{{ $h->incidencia->codigo }}</p>@endif</td>
                        <td class="max-w-xs"><p class="truncate">{{ $h->proyecto->entidad->nombre }}</p><p class="truncate text-xs text-slate-500">{{ $h->proyecto->nombre }}</p></td>
                        <td>{{ $h->tecnico?->name }}</td>
                        <td>{{ $h->receptor_nombre ?? '—' }}
                            @if ($h->requiereVisita())<p class="text-xs text-amber-700">Continúa el {{ $h->proxima_visita?->format('d/m/Y') }}</p>@endif</td>
                        <td><x-badge :color="$h->estado->color()">{{ $h->estado->label() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('hojas.pdf', $h) }}" target="_blank" class="btn btn-ghost btn-sm" title="PDF"><x-icon name="pdf" class="size-4" /></a>
                            <a href="{{ route('hojas.show', $h) }}" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="file" text="No se encontraron hojas de servicio." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($hojas->hasPages())<div class="border-t border-slate-100 p-4">{{ $hojas->links() }}</div>@endif
    </div>
</x-layouts.app>
