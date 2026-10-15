<x-layouts.app :title="$entidad->exists ? 'Editar cliente' : 'Nuevo cliente'">
    <x-page-header :title="$entidad->exists ? 'Editar cliente' : 'Registrar cliente'" subtitle="Entidad del Estado o empresa privada."
                   :back="$entidad->exists ? route('entidades.show', $entidad) : route('entidades.index')" />

    <form method="POST" action="{{ $entidad->exists ? route('entidades.update', $entidad) : route('entidades.store') }}" class="card">
        @csrf
        @if ($entidad->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="nombre" label="Razón social / nombre del cliente" :value="$entidad->nombre" required class="sm:col-span-2" />
                <x-input name="ruc" label="RUC" :value="$entidad->ruc" pattern="(10|15|16|17|20)[0-9]{9}" inputmode="numeric" maxlength="11" data-mensaje="El RUC debe tener 11 dígitos y empezar en 10, 15, 16, 17 o 20." />
                <x-select name="naturaleza" label="Tipo de cliente" :options="\App\Models\Entidad::NATURALEZAS" :value="$entidad->naturaleza" :placeholder="false" required />
                <x-select name="sector" label="Sector" :options="$sectores" :list="true" :value="$entidad->sector" required
                          help="Municipal, salud, minería, educación… Se agregan más en Configuración." />
                <x-input name="departamento" label="Departamento" :value="$entidad->departamento ?? 'Lambayeque'" />
                <x-input name="direccion" label="Dirección" :value="$entidad->direccion" class="sm:col-span-2" />
            </div>
            <div>
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Contacto del cliente</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="contacto_nombre" label="Nombre" :value="$entidad->contacto_nombre" />
                    <x-input name="contacto_cargo" label="Cargo" :value="$entidad->contacto_cargo" />
                    <x-input name="contacto_telefono" label="Teléfono" :value="$entidad->contacto_telefono" pattern="[0-9+\(\)\s\-]{6,20}" inputmode="tel" maxlength="20" data-mensaje="Ingrese un teléfono válido (solo números, espacios, + o guiones)." />
                    <x-input name="contacto_email" type="email" label="Correo" :value="$entidad->contacto_email" />
                    <x-input name="correos_alertas" label="Otros correos para avisos de vencimiento" :value="$entidad->correos_alertas" class="sm:col-span-2"
                             placeholder="logistica@cliente.com, ti@cliente.com"
                             help="Además del correo del contacto. Reciben el aviso cuando una garantía está por vencer. Separe con comas." />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $entidad->activo)) class="rounded border-slate-300 text-brand-600"> Cliente activo
            </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('entidades.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
