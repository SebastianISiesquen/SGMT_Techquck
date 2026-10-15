<x-layouts.app :title="$articulo->codigo">
    <x-page-header :title="$articulo->codigo.' · '.$articulo->nombreCompleto()" :subtitle="$articulo->tipoLabel().($articulo->categoria ? ' · '.$articulo->categoria : '')" :back="route('almacen.index')">
        <x-slot:actions>
            @can('almacen.gestionar')
                <a href="{{ route('almacen.edit', $articulo) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php($cant = fn ($n) => \App\Models\AlmacenArticulo::cantidad($n))
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-3 gap-4">
                <div class="card p-4">
                    <p class="text-xs font-medium text-slate-500">Stock actual</p>
                    <p class="text-2xl font-semibold tabular-nums {{ $articulo->estaBajoMinimo() ? 'text-red-600' : 'text-slate-900' }}">{{ $cant($articulo->stock) }} <span class="text-sm font-normal text-slate-500">{{ $articulo->unidad }}</span></p>
                    @if ($articulo->estaBajoMinimo())<p class="text-xs text-red-600">Bajo el mínimo: reponer</p>@endif
                </div>
                <div class="card p-4">
                    <p class="text-xs font-medium text-slate-500">Stock mínimo</p>
                    <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ $articulo->stock_minimo ? $cant($articulo->stock_minimo) : '—' }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs font-medium text-slate-500">Valor en stock</p>
                    <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ $articulo->costo_unitario ? 'S/ '.number_format($articulo->stock * $articulo->costo_unitario, 2) : '—' }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Kardex (movimientos)</h3></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Fecha</th><th>Movimiento</th><th>Detalle</th><th class="text-right">Cantidad</th><th class="text-right">Stock</th><th>Usuario</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse ($movimientos as $m)
                            <tr>
                                <td class="whitespace-nowrap text-xs tabular-nums">{{ $m->fecha->format('d/m/Y H:i') }}</td>
                                <td><x-badge :color="['entrada' => 'green', 'salida' => 'amber', 'ajuste' => 'slate'][$m->tipo]" :dot="false">{{ $m->tipoLabel() }}</x-badge></td>
                                <td class="text-xs">{{ $m->motivo }}{{ $m->documento ? ' · '.$m->documento : '' }}
                                    @if ($m->hojaServicio)<a href="{{ route('hojas.show', $m->hojaServicio) }}" class="block text-brand-600 hover:underline">Hoja {{ $m->hojaServicio->numero }}</a>@endif
                                    @if ($m->proyecto && ! $m->hojaServicio)<span class="block text-slate-500">{{ $m->proyecto->codigo }} · {{ $m->proyecto->nombre }}</span>@endif</td>
                                <td class="text-right tabular-nums font-medium {{ $m->variacion() < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $m->variacion() > 0 ? '+' : '' }}{{ $cant($m->variacion()) }}</td>
                                <td class="text-right tabular-nums">{{ $cant($m->stock_resultante) }}</td>
                                <td class="text-xs">{{ $m->usuario?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty icon="history" text="Sin movimientos." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($movimientos->hasPages())<div class="border-t border-slate-100 p-4">{{ $movimientos->links() }}</div>@endif
            </div>
        </div>

        <div class="space-y-6">
            @can('almacen.gestionar')
                <form method="POST" action="{{ route('almacen.movimientos.store', $articulo) }}" class="card" x-data="{ tipo: '{{ old('tipo', 'entrada') }}' }">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Registrar movimiento</h3></div>
                    <div class="card-body space-y-3">
                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
                            @foreach (\App\Models\AlmacenMovimiento::TIPOS as $k => $v)
                                <label class="cursor-pointer rounded-md px-2 py-1.5 text-center" :class="tipo === '{{ $k }}' ? 'bg-white shadow text-slate-900' : 'text-slate-500'">
                                    <input type="radio" name="tipo" value="{{ $k }}" x-model="tipo" class="sr-only"> {{ $k === 'ajuste' ? 'Ajuste' : $v }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="tipo !== 'ajuste'"><x-input name="cantidad" type="number" step="0.01" min="0.01" :label="'Cantidad ('.$articulo->unidad.')'" /></div>
                        <div x-show="tipo === 'ajuste'" x-cloak><x-input name="stock_contado" type="number" step="0.01" min="0" label="Stock contado físicamente" help="Se registra la diferencia con el sistema." /></div>
                        <div x-show="tipo === 'entrada'">
                            <x-input name="costo_unitario" type="number" step="0.01" min="0" max="9999999999.99" label="Costo unitario (S/)" :value="$articulo->costo_unitario" />
                        </div>
                        <div x-show="tipo === 'entrada'"><x-input name="documento" label="Factura / guía" /></div>
                        <div x-show="tipo === 'salida'" x-cloak><x-select name="proyecto_id" label="Para el proyecto o servicio" :options="$proyectos" placeholder="— Ninguno —" /></div>
                        <x-input name="motivo" label="Motivo" x-bind:placeholder="tipo === 'entrada' ? 'Compra a proveedor' : (tipo === 'salida' ? 'Entrega al técnico para…' : 'Conteo de inventario')" />
                        <button class="btn btn-primary w-full">Registrar</button>
                        @error('cantidad')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </form>
            @endcan
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos</h3></div>
                <dl class="card-body dl-grid sm:grid-cols-1">
                    <div><dt>Marca / modelo</dt><dd>{{ collect([$articulo->marca, $articulo->modelo])->filter()->implode(' ') ?: '—' }}</dd></div>
                    <div><dt>Ubicación</dt><dd>{{ $articulo->ubicacion ?? '—' }}</dd></div>
                    <div><dt>Costo unitario</dt><dd>{{ $articulo->costo_unitario ? 'S/ '.number_format($articulo->costo_unitario, 2) : '—' }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ $articulo->activo ? 'Activo' : 'Inactivo' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.app>
