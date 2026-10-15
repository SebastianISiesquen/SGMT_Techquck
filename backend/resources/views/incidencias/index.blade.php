<x-layouts.app title="Incidencias">
    <x-page-header title="Incidencias" subtitle="Registro, asignación y seguimiento de incidencias cubiertas por garantía.">
        <x-slot:actions>
            @can('incidencias.gestionar')
                <a href="{{ route('incidencias.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva incidencia</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-input name="buscar" :value="request('buscar')" placeholder="Código, título o descripción…" class="sm:col-span-2" />
            <x-select name="estado" :options="$estados" :value="request('estado')" placeholder="Todos los estados" />
            <x-select name="prioridad" :options="$prioridades" :value="request('prioridad')" placeholder="Todas las prioridades" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" />
            <x-select name="tecnico_id" :options="$tecnicos" :value="request('tecnico_id')" placeholder="Todos los técnicos" />
            <div class="flex gap-2 sm:col-span-2">
                <x-input name="desde" type="date" :value="request('desde')" class="flex-1" title="Reportadas desde" />
                <x-input name="hasta" type="date" :value="request('hasta')" class="flex-1" title="Reportadas hasta" />
                <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
            </div>
            @if (request('proyecto_id'))<input type="hidden" name="proyecto_id" value="{{ request('proyecto_id') }}">@endif
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Incidencia</th><th>Proyecto / Cliente</th><th>Reporte</th><th>Prioridad</th><th>Técnico</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($incidencias as $i)
                    <tr>
                        <td class="max-w-xs">
                            <a href="{{ route('incidencias.show', $i) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $i->titulo }}</a>
                            <p class="text-xs text-slate-500">{{ $i->codigo }}</p>
                        </td>
                        <td class="max-w-xs"><p class="truncate">{{ $i->proyecto->nombre }}</p><p class="truncate text-xs text-slate-500">{{ $i->proyecto->entidad->nombre }}</p></td>
                        <td class="whitespace-nowrap tabular-nums">{{ $i->fecha_reporte->format('d/m/Y H:i') }}
                            @if ($i->estaVencidaSla())<p class="text-xs font-medium text-red-600">Fuera de plazo</p>@endif</td>
                        <td><x-badge :color="$i->prioridad->color()">{{ $i->prioridad->label() }}</x-badge></td>
                        <td>{{ $i->tecnico?->name ?? '—' }}</td>
                        <td><x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge></td>
                        <td class="text-right"><a href="{{ route('incidencias.show', $i) }}" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="alert" text="No se encontraron incidencias." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidencias->hasPages())<div class="border-t border-slate-100 p-4">{{ $incidencias->links() }}</div>@endif
    </div>
</x-layouts.app>
