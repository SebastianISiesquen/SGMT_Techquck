<x-layouts.app :title="$hoja->exists ? 'Editar hoja de servicio' : 'Nueva hoja de servicio'">
    <x-page-header :title="$hoja->exists ? 'Editar hoja '.$hoja->numero : 'Hoja de servicio en campo'"
                   :subtitle="$incidencia ? 'Incidencia '.$incidencia->codigo.' · '.$incidencia->proyecto->entidad->nombre : ($mantenimiento ? $mantenimiento->etiqueta().' · '.$mantenimiento->plan->nombre : 'Mantenimiento preventivo, instalación o inspección.')"
                   :back="$hoja->exists ? route('hojas.show', $hoja) : url()->previous()" />

    @php
        $equipoVacio = ['equipo_id' => null, 'codigo' => null, 'equipo' => '', 'marca' => '', 'modelo' => '', 'serie' => '', 'ubicacion' => '', 'accion' => 'revisado', 'estado_final' => 'operativo', 'observacion' => '', 'checklist' => []];
        $materialVacio = ['articulo_id' => '', 'descripcion' => '', 'cantidad' => 1, 'unidad' => 'und'];
        $equipos = old('equipos', $hoja->exists
            ? $hoja->equipos->map(fn ($e) => [...$e->only(array_keys($equipoVacio)), 'codigo' => $e->inventario?->codigo, 'checklist' => $e->checklist ?? []])->all()
            : ($equiposIniciales ?? [$equipoVacio]));
        $materiales = old('materiales', $hoja->exists ? $hoja->materiales->map->only(array_keys($materialVacio))->all() : []);
        $deIncidencia = (bool) ($incidencia ?? $hoja->incidencia_id);
        $verificaciones = old('verificaciones', $hoja->verificaciones ?? collect(\App\Support\Ajustes::catalogo('verificaciones_previas'))->map(fn ($i) => ['item' => $i, 'resultado' => 'ok'])->all());
        $mediciones = old('mediciones', $hoja->mediciones ?? []);
        $hora = fn ($v) => $v ? substr($v, 0, 5) : null;
    @endphp

    <form method="POST" action="{{ $hoja->exists ? route('hojas.update', $hoja) : route('hojas.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="opcionesDeProyecto('{{ route('proyectos.opciones', '__ID__') }}', '{{ old('garantia_id', $hoja->garantia_id) }}')"
          x-init="proyecto = '{{ old('proyecto_id', $hoja->proyecto_id) }}'; cargar()">
        @csrf
        @if ($hoja->exists) @method('PUT') @endif
        @if ($incidencia)<input type="hidden" name="incidencia_id" value="{{ $incidencia->id }}">@endif
        @if ($mantenimiento)
            <input type="hidden" name="mantenimiento_id" value="{{ $mantenimiento->id }}">
            <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <x-icon name="calendar" class="size-4" /> {{ $mantenimiento->etiqueta() }} programado para el {{ $mantenimiento->fecha_programada->format('d/m/Y') }}. Al guardar la hoja, la visita queda como realizada.
            </div>
        @endif

        @if (! empty($anterior))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="refresh" class="size-4" /> Continuación de la visita del {{ $anterior->fecha->format('d/m/Y') }} (hoja {{ $anterior->numero }})</p>
                @if ($anterior->pendientes)<p class="mt-1">Quedó pendiente: {{ $anterior->pendientes }}</p>@endif
                <p class="mt-1 text-xs text-amber-800">Se cargaron los equipos y el diagnóstico de esa visita. Lo que registre hoy se guarda en una hoja nueva.</p>
            </div>
        @endif

        {{-- 1. Datos del servicio --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">1. Datos del servicio</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($incidencia)
                    <input type="hidden" name="proyecto_id" value="{{ $incidencia->proyecto_id }}">
                    <div class="sm:col-span-2 lg:col-span-4 rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="font-medium text-slate-800">{{ $incidencia->titulo }}</p>
                        <p class="text-slate-500">{{ $incidencia->proyecto->nombre }}</p>
                    </div>
                @else
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="label" for="f_proyecto_id">Proyecto atendido <span class="text-red-500">*</span></label>
                        <select id="f_proyecto_id" name="proyecto_id" x-model="proyecto" @change="cargar()" required class="input">
                            <option value="">Seleccione…</option>
                            @foreach ($proyectos as $id => $texto)<option value="{{ $id }}">{{ $texto }}</option>@endforeach
                        </select>
                        @error('proyecto_id')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="f_garantia_id">Garantía</label>
                        <select id="f_garantia_id" name="garantia_id" x-model="garantia" class="input" :disabled="!proyecto">
                            <option value="">— Sin garantía —</option>
                            <template x-for="o in garantias" :key="o.id"><option :value="o.id" x-text="o.texto" :selected="String(o.id) === String(garantia)"></option></template>
                        </select>
                    </div>
                @endif
                <x-select name="tipo_servicio" label="Tipo de servicio" :options="$tipos" :value="$hoja->tipo_servicio" :placeholder="false" required class="sm:col-span-2" />
                <x-input name="fecha" type="date" label="Fecha" :value="$hoja->fecha" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="hora_llegada" type="time" label="Llegada" :value="$hora($hoja->hora_llegada)" required />
                    <x-input name="hora_salida" type="time" label="Salida" :value="$hora($hoja->hora_salida)" />
                </div>
            </div>
        </div>

        {{-- 2. Verificaciones previas de seguridad --}}
        @if ($verificaciones)
            <div class="card" x-data="{ items: @js(array_values($verificaciones)) }">
                <div class="card-header">
                    <h3 class="card-title">2. Verificaciones previas en sitio</h3>
                    <span class="text-xs text-slate-500">Antes de iniciar el trabajo</span>
                </div>
                <div class="card-body grid gap-2 md:grid-cols-2">
                    <template x-for="(v, i) in items" :key="i">
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2">
                            <input type="hidden" :name="`verificaciones[${i}][item]`" :value="v.item">
                            <input type="hidden" :name="`verificaciones[${i}][resultado]`" :value="v.resultado">
                            <span class="text-sm text-slate-700" x-text="v.item"></span>
                            <div class="flex shrink-0 overflow-hidden rounded-md border border-slate-200 text-xs">
                                <button type="button" class="px-2.5 py-1" @click="v.resultado = 'ok'" :class="v.resultado === 'ok' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600'">Sí</button>
                                <button type="button" class="px-2.5 py-1" @click="v.resultado = 'no'" :class="v.resultado === 'no' ? 'bg-red-600 text-white' : 'bg-white text-slate-600'">No</button>
                                <button type="button" class="px-2.5 py-1" @click="v.resultado = 'na'" :class="v.resultado === 'na' ? 'bg-slate-500 text-white' : 'bg-white text-slate-600'">N/A</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endif

        {{-- 3. Equipos intervenidos (del inventario del cliente o nuevos) --}}
        <div class="card" x-data="filasEquipos(@js(array_values($equipos)), @js($equipoVacio), @js($checklists))">
            <div class="card-header">
                <h3 class="card-title">3. Equipos intervenidos</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <select class="input py-1.5 text-sm" x-show="equipos.length" x-cloak
                            @change="if ($event.target.value) { agregarInventario(equipos.find(o => String(o.id) === $event.target.value)); $event.target.value = '' }">
                        <option value="">+ Equipo del inventario del cliente…</option>
                        <template x-for="o in equipos.filter(o => !filas.some(f => String(f.equipo_id) === String(o.id)))" :key="o.id">
                            <option :value="o.id" x-text="o.texto"></option>
                        </template>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" @click="agregar()"><x-icon name="plus" class="size-4" /> Equipo nuevo</button>
                </div>
            </div>
            <div class="card-body space-y-3">
                <datalist id="categorias-hoja">@foreach (\App\Support\Ajustes::catalogo('categorias_equipo') as $c)<option value="{{ $c }}">@endforeach</datalist>
                <p class="text-xs text-slate-500">Elija los equipos del inventario del cliente o escriba uno nuevo. Si anota la <strong>serie</strong> de un equipo nuevo, se registra solo en el inventario y esta intervención queda en su historial.</p>
                <template x-for="(f, i) in filas" :key="i">
                    <div class="grid gap-2 rounded-lg border p-3 sm:grid-cols-6" :class="f.equipo_id ? 'border-brand-200 bg-brand-50/30' : 'border-slate-200'">
                        <input type="hidden" :name="`equipos[${i}][equipo_id]`" :value="f.equipo_id || ''">
                        <p x-show="f.equipo_id" class="flex items-center gap-1.5 text-xs font-medium text-brand-700 sm:col-span-6">
                            <x-icon name="monitor" class="size-3.5" /> Equipo del inventario <span class="font-mono" x-text="f.codigo"></span>
                        </p>
                        <input :name="`equipos[${i}][equipo]`" x-model="f.equipo" @change="sincronizar(i)" list="categorias-hoja" placeholder="Equipo (ej. Cámara PTZ)" class="input sm:col-span-2">
                        <input :name="`equipos[${i}][marca]`" x-model="f.marca" placeholder="Marca" class="input">
                        <input :name="`equipos[${i}][modelo]`" x-model="f.modelo" placeholder="Modelo" class="input">
                        <input :name="`equipos[${i}][serie]`" x-model="f.serie" placeholder="N° de serie" class="input sm:col-span-2">
                        <input :name="`equipos[${i}][ubicacion]`" x-model="f.ubicacion" placeholder="Ubicación del equipo" class="input sm:col-span-2">
                        <select :name="`equipos[${i}][accion]`" x-model="f.accion" class="input">
                            @foreach ($acciones as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <select :name="`equipos[${i}][estado_final]`" x-model="f.estado_final" class="input">
                            @foreach ($estadosEquipo as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <div class="flex gap-2 sm:col-span-2">
                            <input :name="`equipos[${i}][observacion]`" x-model="f.observacion" :placeholder="f.accion === 'cambio_componente' ? 'Componente cambiado (ej. disco duro)' : 'Observación'" class="input">
                            <button type="button" class="btn btn-ghost px-2 text-slate-400 hover:text-red-600" @click="quitar(i)" title="Quitar"><x-icon name="trash" class="size-4" /></button>
                        </div>

                        {{-- Checklist de mantenimiento según la categoría del equipo --}}
                        <div x-show="f.checklist && f.checklist.length" class="rounded-lg border border-slate-200 bg-white p-3 sm:col-span-6">
                            <p class="mb-2 flex items-center justify-between text-xs font-semibold text-slate-700">
                                <span>Checklist de mantenimiento</span>
                                <button type="button" class="font-medium text-brand-600 hover:underline" @click="f.checklist.forEach(p => { if (!p.resultado) p.resultado = 'ok' })">Marcar pendientes como OK</button>
                            </p>
                            <template x-for="(p, j) in (f.checklist || [])" :key="p.item">
                                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 py-1.5 first:border-0">
                                    <input type="hidden" :name="`equipos[${i}][checklist][${j}][item]`" :value="p.item">
                                    <input type="hidden" :name="`equipos[${i}][checklist][${j}][resultado]`" :value="p.resultado">
                                    <span class="min-w-48 flex-1 text-sm text-slate-700" x-text="p.item"></span>
                                    <div class="flex overflow-hidden rounded-md border border-slate-200 text-xs">
                                        @foreach ($resultadosChecklist as $k => $v)
                                            <button type="button" class="px-2 py-1" @click="p.resultado = '{{ $k }}'"
                                                    :class="p.resultado === '{{ $k }}' ? '{{ ['ok' => 'bg-emerald-600 text-white', 'observado' => 'bg-amber-500 text-white', 'falla' => 'bg-red-600 text-white', 'na' => 'bg-slate-500 text-white'][$k] }}' : 'bg-white text-slate-600 hover:bg-slate-50'">{{ $v }}</button>
                                        @endforeach
                                    </div>
                                    <input x-show="p.resultado === 'observado' || p.resultado === 'falla'" :name="`equipos[${i}][checklist][${j}][nota]`" x-model="p.nota"
                                           placeholder="¿Qué se encontró?" class="input w-full py-1 text-sm sm:w-64">
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <p x-show="!filas.length" class="text-sm text-slate-500">Sin equipos registrados.</p>
                @error('equipos.*')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- 3. Trabajo realizado y materiales --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">4. {{ $deIncidencia ? 'Diagnóstico y trabajo realizado' : 'Trabajo realizado' }}</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                @if ($deIncidencia)
                    <x-textarea name="diagnostico" label="Diagnóstico" :value="$hoja->diagnostico" required rows="3" class="sm:col-span-2"
                                placeholder="¿Qué encontró? ¿Cuál es la causa de la falla?" />
                @endif
                <x-textarea name="trabajo_realizado" label="Trabajo realizado" :value="$hoja->trabajo_realizado" required rows="4" class="sm:col-span-2" />

                {{-- Mediciones (tensión, corriente, temperatura…) --}}
                <div class="sm:col-span-2" x-data="filas(@js(array_values($mediciones)), { parametro: '', valor: '', unidad: '' })">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="label mb-0">Mediciones <span class="font-normal text-slate-400">(opcional)</span></span>
                        <div class="flex flex-wrap gap-1 text-xs">
                            <button type="button" class="rounded bg-slate-100 px-2 py-1 hover:bg-brand-50" @click="['R-S','S-T','T-R','R-N','S-N','T-N','N-T'].forEach(p => filas.push({ parametro: 'Tensión ' + p, valor: '', unidad: 'V' }))">+ Tensiones</button>
                            <button type="button" class="rounded bg-slate-100 px-2 py-1 hover:bg-brand-50" @click="['R','S','T','N'].forEach(p => filas.push({ parametro: 'Corriente I' + p.toLowerCase(), valor: '', unidad: 'A' }))">+ Corrientes</button>
                            <button type="button" class="rounded bg-slate-100 px-2 py-1 hover:bg-brand-50" @click="filas.push({ parametro: 'Temperatura del ambiente', valor: '', unidad: '°C' })">+ Temperatura</button>
                            <button type="button" class="rounded bg-slate-100 px-2 py-1 hover:bg-brand-50" @click="agregar()">+ Otra</button>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <template x-for="(f, i) in filas" :key="i">
                            <div class="flex gap-2">
                                <input :name="`mediciones[${i}][parametro]`" x-model="f.parametro" placeholder="Parámetro" class="input flex-1">
                                <input :name="`mediciones[${i}][valor]`" x-model="f.valor" placeholder="Valor" class="input w-24">
                                <input :name="`mediciones[${i}][unidad]`" x-model="f.unidad" placeholder="Unid." class="input w-16">
                                <button type="button" class="btn btn-ghost px-2 text-slate-400 hover:text-red-600" @click="quitar(i)"><x-icon name="trash" class="size-4" /></button>
                            </div>
                        </template>
                    </div>
                    <p x-show="!filas.length" class="text-sm text-slate-500">Sin mediciones. Use los botones para agregar las habituales.</p>
                </div>

                <div class="sm:col-span-2" x-data="{ ...filas(@js(array_values($materiales)), @js($materialVacio)), articulos: @js($articulos) }">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="label mb-0">Materiales / repuestos utilizados</span>
                        <button type="button" class="btn btn-secondary btn-sm" @click="agregar()"><x-icon name="plus" class="size-4" /> Agregar</button>
                    </div>
                    <template x-for="(f, i) in filas" :key="i">
                        <div class="mb-2 flex flex-wrap gap-2 sm:flex-nowrap">
                            <select :name="`materiales[${i}][articulo_id]`" x-model="f.articulo_id" class="input sm:w-72"
                                    @change="const a = articulos.find(x => String(x.id) === String(f.articulo_id)); if (a) { f.descripcion = a.nombre; f.unidad = a.unidad }">
                                <option value="">Sin descontar del almacén</option>
                                <template x-for="a in articulos" :key="a.id"><option :value="a.id" x-text="a.texto" :selected="String(a.id) === String(f.articulo_id)"></option></template>
                            </select>
                            <input :name="`materiales[${i}][descripcion]`" x-model="f.descripcion" :readonly="!!f.articulo_id" placeholder="Descripción (ej. Conector RJ45 Cat6)" class="input flex-1">
                            <input :name="`materiales[${i}][cantidad]`" x-model="f.cantidad" type="number" step="0.01" min="0" class="input w-24">
                            <input :name="`materiales[${i}][unidad]`" x-model="f.unidad" class="input w-24">
                            <button type="button" class="btn btn-ghost px-2 text-slate-400 hover:text-red-600" @click="quitar(i)"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    </template>
                    <p x-show="!filas.length" class="text-sm text-slate-500">No se usaron materiales.</p>
                    <p x-show="filas.length" class="help">Si elige un artículo del almacén, al guardar la hoja se descuenta de su stock.</p>
                    @error('materiales')<p class="error">{{ $message }}</p>@enderror
                </div>

                @unless ($hoja->exists)
                    <div class="sm:col-span-2" x-data="fotosConLeyenda">
                        <label class="label" for="f_fotos">Fotos del servicio (antes / durante / después)</label>
                        <input id="f_fotos" type="file" name="fotos[]" multiple accept="image/*" @change="elegir($event)"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="fotos.length" x-cloak>
                            <template x-for="(f, i) in fotos" :key="f.url">
                                <div class="overflow-hidden rounded-lg border border-slate-200">
                                    <img :src="f.url" class="h-32 w-full bg-slate-50 object-contain">
                                    <input :name="`fotos_descripcion[${i}]`" x-model="f.leyenda" placeholder="Leyenda (ej. Ubicación del UPS)" class="w-full border-0 border-t border-slate-200 px-2 py-1.5 text-xs">
                                </div>
                            </template>
                        </div>
                        <p class="help">La leyenda aparece debajo de cada foto en el registro fotográfico de la hoja impresa.</p>
                        @error('fotos.*')<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endunless
            </div>
        </div>

        {{-- 5. Resultado de la visita --}}
        <div class="card" x-data="{ resultado: '{{ old('resultado', $hoja->resultado ?? ($deIncidencia ? 'resuelto' : '')) }}' }">
            <div class="card-header"><h3 class="card-title">5. Resultado de la visita</h3></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                @if ($deIncidencia)
                    <div class="sm:col-span-2">
                        <label class="label">¿Cómo quedó la incidencia? <span class="text-red-500">*</span></label>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($resultados as $k => $v)
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm"
                                       :class="resultado === '{{ $k }}' ? '{{ ['resuelto' => 'border-emerald-500 bg-emerald-50', 'parcial' => 'border-amber-500 bg-amber-50', 'pendiente_repuesto' => 'border-blue-500 bg-blue-50', 'no_procede' => 'border-red-500 bg-red-50'][$k] ?? 'border-brand-500' }}' : 'border-slate-200'">
                                    <input type="radio" name="resultado" value="{{ $k }}" x-model="resultado" class="mt-0.5">
                                    <span>{{ $v }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('resultado')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2 grid gap-4 rounded-lg border border-amber-200 bg-amber-50/60 p-4 sm:grid-cols-3" x-show="resultado === 'parcial' || resultado === 'pendiente_repuesto'" x-cloak>
                        <p class="text-sm text-amber-900 sm:col-span-3"><strong>No se terminó hoy:</strong> lo realizado queda guardado en esta hoja. Indique qué falta y cuándo se vuelve; la incidencia sigue en proceso.</p>
                        <x-textarea name="pendientes" label="¿Qué queda pendiente?" :value="$hoja->pendientes" rows="2" class="sm:col-span-2"
                                    placeholder="Ej. Cambiar 4 baterías del banco; llega el repuesto el jueves" x-bind:required="resultado === 'parcial' || resultado === 'pendiente_repuesto'" />
                        <x-input name="proxima_visita" type="date" label="Próxima visita" :value="$hoja->proxima_visita" :min="today()->toDateString()"
                                 x-bind:required="resultado === 'parcial' || resultado === 'pendiente_repuesto'" />
                    </div>
                @endif
                <x-textarea name="observaciones" label="Observaciones" :value="$hoja->observaciones" rows="3" />
                <x-textarea name="recomendaciones" label="Recomendaciones al cliente" :value="$hoja->recomendaciones" rows="3" />
            </div>
        </div>

        {{-- 6. Conformidad: acta firmada a mano --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">6. Conformidad del cliente</h3></div>
            <div class="card-body space-y-4">
                <div class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-600 sm:grid-cols-3">
                    <p><span class="mr-1 inline-flex size-5 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">1</span> Guarde la hoja e imprímala (botón PDF).</p>
                    <p><span class="mr-1 inline-flex size-5 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">2</span> El responsable del cliente la firma y sella.</p>
                    <p><span class="mr-1 inline-flex size-5 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">3</span> Suba la foto o el escaneo del acta firmada.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input name="receptor_nombre" label="Recibe el servicio (nombre)" :value="$hoja->receptor_nombre" />
                    <x-input name="receptor_dni" label="DNI / carné de extranjería" :value="$hoja->receptor_dni" pattern="([0-9]{8}|[A-Za-z0-9]{9,12})" maxlength="12" data-mensaje="Ingrese un DNI de 8 dígitos o un carné de extranjería válido." />
                    <x-input name="receptor_cargo" label="Cargo" :value="$hoja->receptor_cargo" />
                </div>
                @unless ($hoja->exists)
                    <div>
                        <label class="label" for="f_actas">Acta de conformidad firmada <span class="font-normal text-slate-400">(si ya la tiene; también puede subirla después)</span></label>
                        <input id="f_actas" type="file" name="actas[]" multiple accept="image/*,application/pdf"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-emerald-700">
                        @error('actas.*')<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endunless
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $hoja->exists ? route('hojas.show', $hoja) : url()->previous() }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar hoja de servicio</button>
        </div>
    </form>
</x-layouts.app>
