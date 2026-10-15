<x-layouts.app :title="$incidencia->exists ? 'Editar incidencia' : 'Nueva incidencia'">
    <x-page-header :title="$incidencia->exists ? 'Editar incidencia '.$incidencia->codigo : 'Registrar incidencia'"
                   :back="$incidencia->exists ? route('incidencias.show', $incidencia) : route('incidencias.index')" />

    <form method="POST" action="{{ $incidencia->exists ? route('incidencias.update', $incidencia) : route('incidencias.store') }}" enctype="multipart/form-data" class="card"
          x-data="opcionesDeProyecto('{{ route('proyectos.opciones', '__ID__') }}', '{{ old('garantia_id', $incidencia->garantia_id) }}', '{{ old('equipo_id', $incidencia->equipo_id) }}', '{{ old('cobertura', $incidencia->cobertura) }}')"
          x-init="proyecto = '{{ old('proyecto_id', $incidencia->proyecto_id) }}'; cargar()">
        @csrf
        @if ($incidencia->exists) @method('PUT') @endif
        @if (! empty($cotizacion))
            <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id }}">
            <div class="mx-5 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                Orden de trabajo de la cotización aprobada <strong>{{ $cotizacion->numero }}</strong> · {{ $cotizacion->monto($cotizacion->total) }}
            </div>
        @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="f_proyecto_id">Proyecto o servicio <span class="text-red-500">*</span></label>
                    <select id="f_proyecto_id" name="proyecto_id" x-model="proyecto" @change="cargar()" required class="input">
                        <option value="">Seleccione…</option>
                        @foreach ($proyectos as $id => $texto)
                            <option value="{{ $id }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                    @error('proyecto_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="f_garantia_id">Garantía que cubre la incidencia</label>
                    <select id="f_garantia_id" name="garantia_id" x-model="garantia" @change="sugerirCobertura()" class="input" :disabled="!proyecto || cargando">
                        <option value="">— Sin garantía asociada —</option>
                        <template x-for="o in garantias" :key="o.id">
                            <option :value="o.id" x-text="o.texto + ' · ' + o.estado_label" :selected="String(o.id) === String(garantia)"></option>
                        </template>
                    </select>
                    <p x-show="seleccion && seleccion.estado === 'vencida'" x-cloak class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600">
                        <x-icon name="alert" class="size-3.5" /> La garantía seleccionada está vencida: la atención podría no estar cubierta.
                    </p>
                    @error('garantia_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="f_equipo_id">Equipo con falla</label>
                    <select id="f_equipo_id" name="equipo_id" x-model="equipo" @change="alElegirEquipo()" class="input" :disabled="!proyecto || cargando">
                        <option value="">— No identificado / varios equipos —</option>
                        <template x-for="o in equipos" :key="o.id">
                            <option :value="o.id" x-text="o.texto" :selected="String(o.id) === String(equipo)"></option>
                        </template>
                    </select>
                    <p class="help" x-show="proyecto && !cargando && !equipos.length" x-cloak>
                        Este cliente aún no tiene equipos registrados.
                        @can('equipos.gestionar')<a :href="'{{ route('equipos.create') }}?proyecto_id=' + proyecto" target="_blank" class="text-brand-600 hover:underline">Registrar equipo</a>@endcan
                    </p>
                    <p class="help" x-show="equipo" x-cloak>
                        El técnico verá el historial del equipo. <a :href="'{{ url('equipos') }}/' + equipo" target="_blank" class="text-brand-600 hover:underline">Ver antecedentes</a>
                    </p>
                    @error('equipo_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Cobertura de la atención <span class="text-red-500">*</span></label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (\App\Models\Incidencia::COBERTURAS as $k => $v)
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-2.5 text-sm"
                                   :class="cobertura === '{{ $k }}' ? 'border-brand-500 bg-brand-50' : 'border-slate-200'">
                                <input type="radio" name="cobertura" value="{{ $k }}" x-model="cobertura" @change="coberturaManual = true" class="mt-0.5 text-brand-600"> {{ $v }}
                            </label>
                        @endforeach
                    </div>
                    <p class="help" x-show="cobertura === 'sin_cobertura'" x-cloak>Se atiende como servicio a pedido: luego del diagnóstico podrá generar una cotización.</p>
                    @error('cobertura')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-input name="titulo" label="Título" :value="$incidencia->titulo" required class="sm:col-span-2" placeholder="Ej. Cámara sin señal en cruce Av. Balta" />
                <x-textarea name="descripcion" label="Descripción del problema" :value="$incidencia->descripcion" required rows="4" class="sm:col-span-2" />
                <x-select name="prioridad" label="Prioridad" :options="$prioridades" :value="$incidencia->prioridad" :placeholder="false" required />
                <x-input name="fecha_reporte" type="datetime-local" label="Fecha y hora de reporte" :value="$incidencia->fecha_reporte" required />
                <x-select name="canal" label="Canal de reporte" :options="$canales" :list="true" :value="$incidencia->canal" />
                <x-input name="reportado_por" label="Reportado por" :value="$incidencia->reportado_por" placeholder="Nombre de quien reporta en el cliente" />
                <x-input name="ubicacion_detalle" label="Ubicación exacta / equipo" :value="$incidencia->ubicacion_detalle" placeholder="Ej. Poste 14, cruce Balta con Elías Aguirre" />
                <x-input name="fecha_limite" type="datetime-local" label="Fecha límite de atención" :value="$incidencia->fecha_limite" help="Plazo de atención según las condiciones de la garantía." />
            </div>

            @if (! $incidencia->exists)
                @can('incidencias.asignar')
                    <div class="rounded-lg border border-brand-100 bg-brand-50/50 p-4">
                        <x-select name="tecnico_id" label="Asignar de inmediato a un técnico (opcional)" :options="$tecnicos" placeholder="Asignar después" />
                    </div>
                @endcan
                <div>
                    <label class="label">Adjuntos del reporte (oficio, correo, fotos)</label>
                    <input type="file" name="archivos[]" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                </div>
            @endif
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ $incidencia->exists ? route('incidencias.show', $incidencia) : route('incidencias.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
