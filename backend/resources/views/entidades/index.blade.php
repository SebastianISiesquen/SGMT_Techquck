<x-layouts.app title="Clientes">
    <x-page-header title="Clientes" subtitle="Entidades del Estado y empresas privadas a las que la empresa presta servicios.">
        <x-slot:actions>
            @can('entidades.gestionar')
                <a href="{{ route('entidades.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo cliente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
            <x-input name="buscar" :value="request('buscar')" placeholder="Buscar por nombre o RUC…" class="min-w-56 flex-1" />
            <x-select name="naturaleza" :options="\App\Models\Entidad::NATURALEZAS" :value="request('naturaleza')" placeholder="Público y privado" class="w-44" />
            <x-select name="sector" :options="$sectores" :list="true" :value="request('sector')" placeholder="Todos los sectores" class="w-60" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Cliente</th><th>RUC</th><th>Tipo / sector</th><th>Contacto</th><th class="text-center">Proyectos</th><th class="text-center">Garantías</th><th class="text-center">Equipos</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($entidades as $e)
                    <tr>
                        <td><a href="{{ route('entidades.show', $e) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $e->nombre }}</a>
                            <p class="text-xs text-slate-500">{{ $e->direccion }}</p></td>
                        <td class="tabular-nums">{{ $e->ruc ?? '—' }}</td>
                        <td><x-badge :color="$e->naturaleza === 'privado' ? 'violet' : 'blue'">{{ $e->naturalezaLabel() }}</x-badge>
                            <p class="mt-1 text-xs text-slate-500">{{ $e->sector }}</p></td>
                        <td>{{ $e->contacto_nombre ?? '—' }}<p class="text-xs text-slate-500">{{ $e->contacto_telefono }}</p></td>
                        <td class="text-center tabular-nums">{{ $e->proyectos_count }}</td>
                        <td class="text-center tabular-nums">{{ $e->garantias_count }}</td>
                        <td class="text-center tabular-nums">{{ $e->equipos_count }}</td>
                        <td><x-badge :color="$e->activo ? 'green' : 'slate'">{{ $e->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('entidades.show', $e) }}" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a>
                            @can('entidades.gestionar')
                                <a href="{{ route('entidades.edit', $e) }}" class="btn btn-ghost btn-sm" title="Editar"><x-icon name="edit" class="size-4" /></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty icon="building" text="No se encontraron clientes." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($entidades->hasPages())<div class="border-t border-slate-100 p-4">{{ $entidades->links() }}</div>@endif
    </div>
</x-layouts.app>
