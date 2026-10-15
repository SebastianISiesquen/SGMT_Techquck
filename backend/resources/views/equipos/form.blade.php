<x-layouts.app :title="$equipo->exists ? 'Editar equipo' : 'Nuevo equipo'">
    <x-page-header :title="$equipo->exists ? 'Editar equipo '.$equipo->codigo : 'Registrar equipo'"
                   subtitle="Al guardar se asigna un código interno (EQ-000001) para el sticker con QR."
                   :back="$equipo->exists ? route('equipos.show', $equipo) : route('equipos.index')" />

    <form method="POST" action="{{ $equipo->exists ? route('equipos.update', $equipo) : route('equipos.store') }}" class="card"
          x-data="{ proyecto: '{{ old('proyecto_id', $equipo->proyecto_id) }}', garantia: '{{ old('garantia_id', $equipo->garantia_id) }}', garantias: @js($garantias) }">
        @csrf
        @if ($equipo->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="entidad_id" label="Cliente propietario del equipo" :options="$entidades" :value="$equipo->entidad_id" required
                          help="Si el proyecto es un subcontrato, elija el cliente final (donde está instalado)." />
                <div>
                    <label class="label" for="f_proyecto_id">Proyecto o servicio</label>
                    <select id="f_proyecto_id" name="proyecto_id" x-model="proyecto" @change="garantia = ''" class="input">
                        <option value="">— Ninguno —</option>
                        @foreach ($proyectos as $id => $texto)<option value="{{ $id }}">{{ $texto }}</option>@endforeach
                    </select>
                    @error('proyecto_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="f_garantia_id">Garantía que lo cubre</label>
                    <select id="f_garantia_id" name="garantia_id" x-model="garantia" class="input" :disabled="!proyecto">
                        <option value="">— La vigente del proyecto —</option>
                        <template x-for="g in garantias.filter(g => String(g.proyecto_id) === String(proyecto))" :key="g.id">
                            <option :value="g.id" x-text="g.texto" :selected="String(g.id) === String(garantia)"></option>
                        </template>
                    </select>
                    @error('garantia_id')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Identificación</h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-select name="categoria" label="Categoría" :options="$categorias" :list="true" :value="$equipo->categoria" required />
                    <x-input name="marca" label="Marca" :value="$equipo->marca" />
                    <x-input name="modelo" label="Modelo" :value="$equipo->modelo" />
                    <x-input name="serie" label="N° de serie" :value="$equipo->serie" help="Si la placa ya no se lee, déjelo vacío: el código del sticker lo identifica." />
                    <x-input name="descripcion" label="Descripción" :value="$equipo->descripcion" class="sm:col-span-2 lg:col-span-4" placeholder="Ej. Laptop de la jefatura de logística · Cámara del ingreso principal" />
                </div>
            </div>

            <div>
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Ubicación y responsable</h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input name="ubicacion" label="Ubicación" :value="$equipo->ubicacion" class="sm:col-span-2" placeholder="Sede central, 2.º piso · Poste 14 Av. Balta" />
                    <x-input name="area" label="Área" :value="$equipo->area" />
                    <x-input name="responsable" label="Responsable / usuario" :value="$equipo->responsable" help="Persona que lo usa o tiene a cargo." />
                    <x-input name="fecha_instalacion" type="date" label="Fecha de instalación / entrega" :value="$equipo->fecha_instalacion" />
                    <x-select name="estado" label="Estado" :options="\App\Models\Equipo::ESTADOS" :value="$equipo->estado" :placeholder="false" required />
                    <x-textarea name="observaciones" label="Observaciones" :value="$equipo->observaciones" rows="2" class="sm:col-span-2" />
                </div>
                @if ($equipo->exists)
                    <p class="help mt-2">Los cambios de responsable o de ubicación quedan registrados en el historial del equipo.</p>
                @endif
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ $equipo->exists ? route('equipos.show', $equipo) : route('equipos.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
