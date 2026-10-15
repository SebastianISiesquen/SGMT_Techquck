<x-layouts.app :title="$garantia->exists ? 'Editar garantía' : 'Nueva garantía'">
    <x-page-header :title="$garantia->exists ? 'Editar garantía '.$garantia->codigo : 'Registrar garantía'"
                   :back="$garantia->exists ? route('garantias.show', $garantia) : route('garantias.index')" />

    <form method="POST" action="{{ $garantia->exists ? route('garantias.update', $garantia) : route('garantias.store') }}" enctype="multipart/form-data" class="card"
          x-data="duracionGarantia('{{ old('fecha_inicio', $garantia->fecha_inicio?->format('Y-m-d')) }}', '{{ old('duracion_meses', $garantia->duracion_meses) }}', '{{ old('fecha_vencimiento', $garantia->fecha_vencimiento?->format('Y-m-d')) }}')">
        @csrf
        @if ($garantia->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="proyecto_id" label="Proyecto o servicio" :options="$proyectos" :value="$garantia->proyecto_id" required class="sm:col-span-2" />
                <x-select name="tipo" label="Tipo de garantía" :options="$tipos" :list="true" :value="$garantia->tipo" required />
                <x-input name="descripcion" label="Descripción" :value="$garantia->descripcion" required placeholder="Ej. Garantía de 120 cámaras y 3 NVR" />
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Vigencia</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="f_fecha_inicio">Fecha de inicio <span class="text-red-500">*</span></label>
                        <input id="f_fecha_inicio" type="date" name="fecha_inicio" x-model="inicio" @change="calcular()" required class="input">
                        @error('fecha_inicio')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="f_duracion">Duración</label>
                        <select id="f_duracion" name="duracion_meses" x-model="meses" @change="calcular()" class="input">
                            <option value="">Manual (elegir fecha)</option>
                            @foreach (\App\Models\Garantia::DURACIONES as $m => $texto)
                                <option value="{{ $m }}">{{ $texto }}</option>
                            @endforeach
                            <template x-if="meses && !{{ json_encode(array_keys(\App\Models\Garantia::DURACIONES)) }}.includes(Number(meses))">
                                <option :value="meses" x-text="meses + ' meses'"></option>
                            </template>
                        </select>
                        <div class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                            Otro: <input type="number" min="1" max="240" class="w-16 rounded border border-slate-200 px-1 py-0.5" placeholder="meses"
                                         @change="meses = $event.target.value; calcular()"> meses
                        </div>
                    </div>
                    <div>
                        <label class="label" for="f_fecha_vencimiento">Vencimiento <span class="text-red-500">*</span></label>
                        <input id="f_fecha_vencimiento" type="date" name="fecha_vencimiento" x-model="vence" @input="meses = ''" required class="input">
                        @error('fecha_vencimiento')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="help mt-2">Implementaciones: normalmente 5 años. Mantenimientos o servicios: por ejemplo 3 meses. Si cambia la fecha a mano, la duración queda como «Manual».</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="orden_compra" label="Orden de compra" :value="$garantia->orden_compra" help="A qué orden de compra pertenece esta garantía." />
                <x-input name="monto" type="number" step="0.01" min="0" max="9999999999.99" label="Monto garantizado (S/)" :value="$garantia->monto" />
                <x-input name="proveedor" label="Proveedor / fabricante" :value="$garantia->proveedor" />
                <x-input name="numero_documento" label="N° certificado del fabricante" :value="$garantia->numero_documento" />
                <x-textarea name="cobertura" label="Cobertura" :value="$garantia->cobertura" rows="3" class="sm:col-span-2" help="Qué equipos, servicios o fallas cubre. Aparece en la carta de garantía." />
                <x-textarea name="condiciones" label="Condiciones y exclusiones" :value="$garantia->condiciones" rows="3" class="sm:col-span-2" />
                <x-textarea name="observaciones" label="Observaciones" :value="$garantia->observaciones" rows="2" class="sm:col-span-2"
                            help="Notas internas. Ej.: «Incluye 4 mantenimientos preventivos; este es el 2.º de 4»." />
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="notificar_cliente" value="0">
                    <input type="checkbox" name="notificar_cliente" value="1" @checked(old('notificar_cliente', $garantia->notificar_cliente)) class="rounded border-slate-300 text-brand-600">
                    Avisar al cliente por correo cuando la garantía esté por vencer
                </label>
                @if ($garantia->exists)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="anulada" value="0">
                        <input type="checkbox" name="anulada" value="1" @checked(old('anulada', $garantia->anulada)) class="rounded border-slate-300 text-red-600"> Garantía anulada (deja de mostrarse en alertas)
                    </label>
                @endif
            </div>

            @unless ($garantia->exists)
                <div>
                    <label class="label">Documentos de la garantía (opcional)</label>
                    <input type="file" name="archivos[]" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                    <p class="help">Certificados del fabricante u otros. La carta de garantía de la empresa la genera el sistema.</p>
                </div>
            @endunless
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ url()->previous() }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
