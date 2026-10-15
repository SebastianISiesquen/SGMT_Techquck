<x-layouts.app :title="$articulo->exists ? 'Editar artículo' : 'Nuevo artículo'">
    <x-page-header :title="$articulo->exists ? 'Editar '.$articulo->codigo : 'Registrar artículo en el almacén'"
                   :back="$articulo->exists ? route('almacen.show', $articulo) : route('almacen.index')" />

    <form method="POST" action="{{ $articulo->exists ? route('almacen.update', $articulo) : route('almacen.store') }}" class="card">
        @csrf
        @if ($articulo->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="tipo" label="Tipo" :options="\App\Models\AlmacenArticulo::TIPOS" :value="$articulo->tipo" :placeholder="false" required
                          help="Consumible: se gasta (pasta térmica, limpia contactos, conectores). Repuesto: se instala en un equipo (disco, fuente, cámara)." />
                <x-input name="nombre" label="Nombre" :value="$articulo->nombre" required placeholder="Ej. Pasta térmica · Disco SSD 512 GB" />
                <div>
                    <label class="label" for="f_categoria">Categoría</label>
                    <input id="f_categoria" name="categoria" list="cats" value="{{ old('categoria', $articulo->categoria) }}" class="input">
                    <datalist id="cats">@foreach ($categorias as $c)<option value="{{ $c }}">@endforeach</datalist>
                </div>
                <x-input name="codigo" label="Código" :value="$articulo->codigo" help="Vacío: se genera CON-0001 o REP-0001." />
                <x-input name="marca" label="Marca" :value="$articulo->marca" />
                <x-input name="modelo" label="Modelo" :value="$articulo->modelo" />
                <x-input name="unidad" label="Unidad" :value="$articulo->unidad" required list="unidades" />
                <datalist id="unidades">@foreach (['und', 'caja', 'rollo', 'metro', 'frasco', 'tubo', 'paquete', 'kit'] as $u)<option value="{{ $u }}">@endforeach</datalist>
                <x-input name="ubicacion" label="Ubicación en el almacén" :value="$articulo->ubicacion" placeholder="Estante A2" />
                <x-input name="stock_minimo" type="number" step="0.01" min="0" label="Stock mínimo" :value="$articulo->stock_minimo"
                         help="Al llegar a este nivel se avisa para reponer." />
                <x-input name="costo_unitario" type="number" step="0.01" min="0" max="9999999999.99" label="Costo unitario (S/)" :value="$articulo->costo_unitario" />
                @unless ($articulo->exists)
                    <x-input name="stock_inicial" type="number" step="0.01" min="0" label="Stock inicial" :value="0" />
                    <x-input name="documento" label="Documento de ingreso" placeholder="Factura / guía" />
                @endunless
            </div>
            @if ($articulo->exists)
                <p class="help">El stock ({{ \App\Models\AlmacenArticulo::cantidad($articulo->stock) }} {{ $articulo->unidad }}) no se edita aquí: registre una entrada, salida o ajuste desde el kardex.</p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" @checked(old('activo', $articulo->activo)) class="rounded border-slate-300 text-brand-600"> Artículo activo
                </label>
            @endif
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ $articulo->exists ? route('almacen.show', $articulo) : route('almacen.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
