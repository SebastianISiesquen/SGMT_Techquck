<x-layouts.app :title="$incidencia->exists ? 'Editar incidencia' : 'Nueva incidencia'">
    <x-page-header :title="$incidencia->exists ? 'Editar incidencia '.$incidencia->codigo : 'Registrar incidencia'"
                   :back="$incidencia->exists ? route('incidencias.show', $incidencia) : route('incidencias.index')" />

    <form method="POST" action="{{ $incidencia->exists ? route('incidencias.update', $incidencia) : route('incidencias.store') }}" enctype="multipart/form-data" class="card"
          x-data="opcionesDeProyecto('{{ route('proyectos.opciones', '__ID__') }}', '{{ old('garantia_id', $incidencia->garantia_id) }}', @js(old('equipos', $equiposIniciales ?? [])), '{{ old('cobertura', $incidencia->cobertura) }}')"
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
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="label">Equipos con falla</span>
                        <span class="text-xs text-slate-500" x-show="equipos.length" x-cloak>
                            <span x-text="seleccionados.length"></span> de <span x-text="equipos.length"></span> equipos marcados
                        </span>
                    </div>
                    <p class="help -mt-1 mb-2">Marque todos los equipos que fallan en este reporte (por ejemplo 3 de las 10 cámaras). En la visita se registra el diagnóstico de cada uno y se emite un informe técnico por equipo.</p>
                    <template x-for="id in seleccionados" :key="id"><input type="hidden" name="equipos[]" :value="id"></template>

                    <div class="rounded-lg border border-slate-200" x-show="proyecto && !cargando && equipos.length" x-cloak>
                        <div class="border-b border-slate-100 p-2">
                            <input type="search" x-model="buscarEquipo" placeholder="Buscar por código, serie o ubicación…" class="input py-1.5 text-sm">
                        </div>
                        <div class="max-h-64 divide-y divide-slate-100 overflow-y-auto">
                            <template x-for="o in equiposFiltrados" :key="o.id">
                                <label class="flex cursor-pointer items-start gap-2.5 px-3 py-2 text-sm hover:bg-slate-50"
                                       :class="seleccionados.includes(String(o.id)) && 'bg-red-50/60'">
                                    <input type="checkbox" class="mt-0.5 rounded text-red-600" :checked="seleccionados.includes(String(o.id))" @change="marcarEquipo(o.id)">
                                    <span class="min-w-0">
                                        <span class="block text-slate-800" x-text="o.texto"></span>
                                        <span class="block text-xs text-slate-500" x-show="o.serie" x-text="'S/N ' + o.serie"></span>
                                    </span>
                                </label>
                            </template>
                            <p class="px-3 py-2 text-sm text-slate-500" x-show="!equiposFiltrados.length">Ningún equipo coincide con la búsqueda.</p>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5" x-show="equiposElegidos.length" x-cloak>
                        <template x-for="o in equiposElegidos" :key="o.id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-200">
                                <span x-text="o.texto.split(' · ')[0]"></span>
                                <button type="button" @click="marcarEquipo(o.id)" class="hover:text-red-900" title="Quitar">&times;</button>
                            </span>
                        </template>
                    </div>
                    <p class="help" x-show="proyecto && !cargando && !equipos.length" x-cloak>
                        Este cliente aún no tiene equipos registrados.
                        @can('equipos.gestionar')<a :href="'{{ route('equipos.create') }}?proyecto_id=' + proyecto" target="_blank" class="text-brand-600 hover:underline">Registrar equipo</a>@endcan
                    </p>
                    <p class="help" x-show="!proyecto" x-cloak>Primero elija el proyecto para ver sus equipos instalados.</p>
                    @error('equipos')<p class="error">{{ $message }}</p>@enderror
                    @error('equipos.*')<p class="error">{{ $message }}</p>@enderror
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
