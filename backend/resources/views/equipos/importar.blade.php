<x-layouts.app title="Importar equipos">
    <x-page-header title="Importar equipos desde Excel" subtitle="Cargue de una vez el inventario de un cliente (por ejemplo, sus 20 laptops)." :back="route('equipos.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('equipos.importar.store') }}" enctype="multipart/form-data" class="card lg:col-span-2">
            @csrf
            <div class="card-body space-y-4">
                <x-select name="entidad_id" label="Cliente propietario de los equipos" :options="$entidades" :value="$entidadId" required />
                <x-select name="proyecto_id" label="Proyecto o servicio (opcional)" :options="$proyectos" :value="$proyectoId" placeholder="— Ninguno —"
                          help="Si elige un proyecto, los equipos quedan cubiertos por su garantía vigente." />
                <div>
                    <label class="label" for="f_archivo">Archivo Excel <span class="text-red-500">*</span></label>
                    <input id="f_archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                    @error('archivo')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <a href="{{ route('equipos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary"><x-icon name="upload" class="size-4" /> Importar</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Cómo preparar el archivo</h3></div>
            <div class="card-body space-y-3 text-sm text-slate-600">
                <a href="{{ route('equipos.plantilla') }}" class="btn btn-secondary w-full"><x-icon name="download" class="size-4" /> Descargar plantilla</a>
                <p>La primera fila debe tener estos encabezados:</p>
                <p class="rounded bg-slate-50 p-2 font-mono text-xs">categoria, marca, modelo, serie, descripcion, ubicacion, area, responsable, fecha_instalacion, observaciones</p>
                <ul class="list-disc space-y-1 pl-5 text-xs">
                    <li>Solo <strong>categoria</strong> es obligatoria.</li>
                    <li>Las series que el cliente ya tiene registradas se omiten (no se duplican).</li>
                    <li>Fecha en formato 2026-03-15 o 15/03/2026.</li>
                    <li>Cada equipo recibe su código EQ-… para imprimir el sticker con QR.</li>
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
