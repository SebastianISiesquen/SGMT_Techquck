<x-layouts.app title="Almacén">
    <x-page-header title="Almacén" subtitle="Consumibles y repuestos de la empresa. Lo que el técnico usa en una hoja de servicio se descuenta del stock.">
        <x-slot:actions>
            @can('almacen.gestionar')
                <a href="{{ route('almacen.create', ['tipo' => 'repuesto']) }}" class="btn btn-secondary"><x-icon name="plus" class="size-4" /> Repuesto</a>
                <a href="{{ route('almacen.create', ['tipo' => 'consumible']) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Consumible</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Artículos activos" :value="$resumen['articulos']" icon="box" tone="brand" />
        <x-stat label="Bajo el stock mínimo" :value="$resumen['bajo_minimo']" icon="alert" :tone="$resumen['bajo_minimo'] ? 'red' : 'slate'" :href="route('almacen.index', ['stock' => 'bajo'])" />
        <x-stat label="Valor del inventario" :value="'S/ '.number_format($resumen['valor'], 2)" icon="receipt" tone="green" />
        <x-stat label="Salidas este mes" :value="$resumen['salidas_mes']" icon="upload" tone="violet" />
    </div>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-input name="buscar" :value="request('buscar')" placeholder="Nombre, código, marca, modelo…" class="lg:col-span-2" />
            <x-select name="tipo" :options="\App\Models\AlmacenArticulo::TIPOS" :value="request('tipo')" placeholder="Consumibles y repuestos" />
            <x-select name="categoria" :options="$categorias" :list="true" :value="request('categoria')" placeholder="Todas las categorías" />
            <div class="flex gap-2">
                <x-select name="stock" :options="['bajo' => 'Solo bajo el mínimo']" :value="request('stock')" placeholder="Todo el stock" class="flex-1" />
                <button class="btn btn-secondary" title="Filtrar"><x-icon name="search" class="size-4" /></button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Código</th><th>Artículo</th><th>Tipo</th><th>Ubicación</th><th class="text-right">Stock</th><th class="text-right">Mínimo</th><th class="text-right">Costo unit.</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($articulos as $a)
                    <tr>
                        <td class="whitespace-nowrap font-mono text-xs"><a href="{{ route('almacen.show', $a) }}" class="font-semibold text-brand-600 hover:underline">{{ $a->codigo }}</a></td>
                        <td><a href="{{ route('almacen.show', $a) }}" class="font-medium text-slate-800 hover:text-brand-600">{{ $a->nombre }}</a>
                            <p class="text-xs text-slate-500">{{ collect([$a->marca, $a->modelo, $a->categoria])->filter()->implode(' · ') }}</p></td>
                        <td><x-badge :color="$a->tipo === 'consumible' ? 'blue' : 'violet'" :dot="false">{{ $a->tipoLabel() }}</x-badge></td>
                        <td class="text-xs">{{ $a->ubicacion ?? '—' }}</td>
                        <td class="text-right tabular-nums">
                            <span class="font-semibold {{ $a->estaBajoMinimo() ? 'text-red-600' : 'text-slate-900' }}">{{ \App\Models\AlmacenArticulo::cantidad($a->stock) }}</span>
                            <span class="text-xs text-slate-500">{{ $a->unidad }}</span>
                            @if ($a->estaBajoMinimo())<x-badge color="red" class="ml-1">Reponer</x-badge>@endif
                        </td>
                        <td class="text-right tabular-nums text-slate-500">{{ $a->stock_minimo ? \App\Models\AlmacenArticulo::cantidad($a->stock_minimo) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $a->costo_unitario ? 'S/ '.number_format($a->costo_unitario, 2) : '—' }}</td>
                        <td class="text-right"><a href="{{ route('almacen.show', $a) }}" class="btn btn-ghost btn-sm" title="Kardex"><x-icon name="history" class="size-4" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="box" text="No hay artículos registrados en el almacén." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($articulos->hasPages())<div class="border-t border-slate-100 p-4">{{ $articulos->links() }}</div>@endif
    </div>
</x-layouts.app>
