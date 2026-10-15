<x-layouts.app :title="$cotizacion->exists ? 'Editar cotización' : 'Nueva cotización'">
    <x-page-header :title="$cotizacion->exists ? 'Editar cotización '.$cotizacion->numero : 'Nueva cotización'"
                   :back="$cotizacion->exists ? route('cotizaciones.show', $cotizacion) : route('cotizaciones.index')" />

    <form method="POST" action="{{ $cotizacion->exists ? route('cotizaciones.update', $cotizacion) : route('cotizaciones.store') }}" class="space-y-6"
          x-data="{ items: @js(array_values($items)), igv: {{ $igv }}, moneda: '{{ old('moneda', $cotizacion->moneda) }}',
                    get subtotal() { return this.items.reduce((s, i) => s + (Number(i.cantidad) || 0) * (Number(i.precio_unitario) || 0), 0) },
                    fmt(n) { return (this.moneda === 'USD' ? 'US$ ' : 'S/ ') + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } }">
        @csrf
        @if ($cotizacion->exists) @method('PUT') @endif
        <input type="hidden" name="proyecto_id" value="{{ old('proyecto_id', $cotizacion->proyecto_id) }}">
        <input type="hidden" name="incidencia_id" value="{{ old('incidencia_id', $cotizacion->incidencia_id) }}">
        <input type="hidden" name="equipo_id" value="{{ old('equipo_id', $cotizacion->equipo_id) }}">

        <div class="card">
            <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select name="entidad_id" label="Cliente" :options="$entidades" :value="$cotizacion->entidad_id" required class="sm:col-span-2" />
                <x-input name="fecha" type="date" label="Fecha" :value="$cotizacion->fecha" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="validez_dias" type="number" min="1" label="Validez (días)" :value="$cotizacion->validez_dias" required />
                    <div>
                        <label class="label" for="f_moneda">Moneda</label>
                        <select id="f_moneda" name="moneda" x-model="moneda" class="input">
                            @foreach (\App\Models\Cotizacion::MONEDAS as $k => $s)<option value="{{ $k }}">{{ $k }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <x-input name="asunto" label="Asunto" :value="$cotizacion->asunto" required class="sm:col-span-2 lg:col-span-4" />
                <x-textarea name="diagnostico" label="Diagnóstico / descripción del requerimiento" :value="$cotizacion->diagnostico" rows="3" class="sm:col-span-2 lg:col-span-4" />
                @if ($cotizacion->incidencia_id || $cotizacion->equipo_id)
                    <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-4">Vinculada a
                        {{ collect([$cotizacion->incidencia?->codigo ? 'la incidencia '.$cotizacion->incidencia->codigo : null, $cotizacion->equipo ? 'el equipo '.$cotizacion->equipo->codigo.' ('.$cotizacion->equipo->nombre().')' : null])->filter()->implode(' y ') }}.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Detalle</h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="items.push({ descripcion: '', cantidad: 1, unidad: 'und', precio_unitario: '' })"><x-icon name="plus" class="size-4" /> Ítem</button></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Descripción</th><th class="w-24">Cant.</th><th class="w-28">Unidad</th><th class="w-32">P. unitario</th><th class="w-32 text-right">Importe</th><th class="w-10"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, i) in items" :key="i">
                            <tr>
                                <td><input :name="`items[${i}][descripcion]`" x-model="item.descripcion" class="input" placeholder="Diagnóstico, mano de obra, repuesto…"></td>
                                <td><input :name="`items[${i}][cantidad]`" x-model="item.cantidad" type="number" step="0.01" min="0" class="input"></td>
                                <td><input :name="`items[${i}][unidad]`" x-model="item.unidad" class="input"></td>
                                <td><input :name="`items[${i}][precio_unitario]`" x-model="item.precio_unitario" type="number" step="0.01" min="0" class="input"></td>
                                <td class="text-right tabular-nums" x-text="fmt((Number(item.cantidad) || 0) * (Number(item.precio_unitario) || 0))"></td>
                                <td><button type="button" class="btn btn-ghost btn-sm text-red-600" @click="items.splice(i, 1)" x-show="items.length > 1"><x-icon name="trash" class="size-4" /></button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="4" class="text-right text-slate-500">Subtotal</td><td class="text-right tabular-nums" x-text="fmt(subtotal)"></td><td></td></tr>
                        <tr><td colspan="4" class="text-right text-slate-500">IGV ({{ $igv }}%)</td><td class="text-right tabular-nums" x-text="fmt(subtotal * igv / 100)"></td><td></td></tr>
                        <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="text-right font-semibold tabular-nums" x-text="fmt(subtotal * (1 + igv / 100))"></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            @error('items')<p class="error px-5 pb-3">{{ $message }}</p>@enderror
        </div>

        <div class="card">
            <div class="card-body">
                <x-textarea name="condiciones" label="Condiciones comerciales" :value="$cotizacion->condiciones" rows="4" />
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <a href="{{ url()->previous() }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </form>
</x-layouts.app>
