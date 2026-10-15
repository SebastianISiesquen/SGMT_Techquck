<x-layouts.app title="Garantías">
    <x-page-header title="Garantías" subtitle="Control de vigencia de las garantías de los proyectos ejecutados.">
        <x-slot:actions>
            @can('garantias.gestionar')
                <a href="{{ route('garantias.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva garantía</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-stat label="Vigentes" :value="$resumen['vigente']" icon="check-circle" tone="green" :href="route('garantias.index', ['estado' => 'vigente'])" />
        <x-stat label="Próximas a vencer" :value="$resumen['por_vencer']" icon="clock" tone="amber" :href="route('garantias.index', ['estado' => 'por_vencer'])" />
        <x-stat label="Vencidas" :value="$resumen['vencida']" icon="x-circle" tone="red" :href="route('garantias.index', ['estado' => 'vencida'])" />
    </div>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-input name="buscar" :value="request('buscar')" placeholder="Código, descripción o proyecto…" class="lg:col-span-2" />
            <x-select name="estado" :options="$estados" :value="request('estado')" placeholder="Todos los estados" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" />
            <x-select name="tipo" :options="$tipos" :list="true" :value="request('tipo')" placeholder="Todos los tipos" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
            <x-input name="vence_desde" type="date" :value="request('vence_desde')" label="Vence desde" />
            <x-input name="vence_hasta" type="date" :value="request('vence_hasta')" label="Vence hasta" />
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Garantía</th><th>Proyecto / Cliente</th><th>Vigencia</th><th class="text-right">Días rest.</th><th class="text-center">Inc. abiertas</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($garantias as $g)
                    <tr>
                        <td><a href="{{ route('garantias.show', $g) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $g->tipo }}</a>
                            <p class="text-xs text-slate-500">{{ $g->codigo }}{{ $g->proveedor ? ' · '.$g->proveedor : '' }}</p></td>
                        <td class="max-w-xs"><p class="truncate">{{ $g->proyecto->nombre }}</p><p class="truncate text-xs text-slate-500">{{ $g->proyecto->entidad->nombre }}</p></td>
                        <td class="whitespace-nowrap tabular-nums">{{ $g->fecha_inicio->format('d/m/Y') }} – {{ $g->fecha_vencimiento->format('d/m/Y') }}</td>
                        <td class="text-right tabular-nums {{ $g->diasRestantes() < 0 ? 'text-slate-400' : '' }}">{{ $g->diasRestantes() >= 0 ? $g->diasRestantes() : '—' }}</td>
                        <td class="text-center tabular-nums">{{ $g->incidencias_abiertas_count }}</td>
                        <td><x-badge :color="$g->estado->color()">{{ $g->estado->label() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('garantias.show', $g) }}" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a>
                            @can('garantias.gestionar')
                                <a href="{{ route('garantias.edit', $g) }}" class="btn btn-ghost btn-sm" title="Editar"><x-icon name="edit" class="size-4" /></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="shield" text="No se encontraron garantías." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($garantias->hasPages())<div class="border-t border-slate-100 p-4">{{ $garantias->links() }}</div>@endif
    </div>
</x-layouts.app>
