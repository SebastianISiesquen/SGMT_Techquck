<x-layouts.app :title="$equipo->codigo">
    <x-page-header :title="$equipo->codigo.' · '.trim($equipo->categoria.' '.$equipo->marca.' '.$equipo->modelo)"
                   :subtitle="$equipo->entidad->nombre.($equipo->serie ? ' · S/N '.$equipo->serie : ' · Sin serie legible')" :back="route('equipos.index', ['entidad_id' => $equipo->entidad_id])">
        <x-slot:actions>
            <a href="{{ route('equipos.hoja-vida', $equipo) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> Hoja de vida (PDF)</a>
            <a href="{{ route('equipos.etiquetas', ['ids' => [$equipo->id]]) }}" target="_blank" class="btn btn-secondary"><x-icon name="qr" class="size-4" /> Sticker</a>
            @can('equipos.gestionar')
                <a href="{{ route('equipos.edit', $equipo) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
            @endcan
            @can('cotizaciones.gestionar')
                @unless ($garantiaVigente)
                    <a href="{{ route('cotizaciones.create', ['equipo_id' => $equipo->id]) }}" class="btn btn-secondary"><x-icon name="receipt" class="size-4" /> Cotizar</a>
                @endunless
            @endcan
            @can('incidencias.gestionar')
                @if ($equipo->proyecto_id)
                    <a href="{{ route('incidencias.create', ['proyecto_id' => $equipo->proyecto_id, 'equipo_id' => $equipo->id]) }}" class="btn btn-primary"><x-icon name="alert" class="size-4" /> Reportar falla</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Resumen --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Estado actual</p>
            <p class="mt-1"><x-badge :color="$equipo->estadoColor()" class="px-3 py-1 text-sm">{{ $equipo->estadoLabel() }}</x-badge></p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Garantía</p>
            @if ($garantiaVigente)
                <a href="{{ route('garantias.show', $garantiaVigente) }}" class="mt-1 block text-sm font-semibold text-emerald-700 hover:underline">Vigente · {{ $garantiaVigente->diasRestantes() }} días</a>
                <p class="text-xs text-slate-500">{{ $garantiaVigente->codigo }} · vence {{ $garantiaVigente->fecha_vencimiento->format('d/m/Y') }}</p>
            @else
                <p class="mt-1 text-sm font-semibold text-red-600">Sin garantía vigente</p>
                <p class="text-xs text-slate-500">Las atenciones se cotizan aparte.</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Historial</p>
            <p class="mt-1 text-sm text-slate-700"><strong>{{ $resumen['fallas'] }}</strong> falla(s) · <strong>{{ $resumen['mantenimientos'] }}</strong> mant. · <strong>{{ $resumen['componentes'] }}</strong> cambio(s)</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-slate-500">Último mantenimiento</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $resumen['ultimo_mantenimiento']?->format('d/m/Y') ?? 'Sin registro' }}</p>
            <p class="text-xs text-slate-500">Última falla: {{ $resumen['ultima_falla']?->format('d/m/Y') ?? '—' }}</p>
        </div>
    </div>

    @if ($antecedentes->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="flex items-center gap-2 text-sm font-semibold text-amber-900"><x-icon name="alert" class="size-4" /> Antecedentes a tener en cuenta antes de atender este equipo</p>
            <ul class="mt-2 space-y-1.5 text-sm text-amber-900">
                @foreach ($antecedentes as $a)
                    <li><span class="font-medium">{{ $a->fecha->format('d/m/Y') }} · {{ $a->tipoLabel() }}:</span> {{ $a->titulo }}{{ $a->componente ? ' ('.$a->componente.')' : '' }}
                        @if ($a->detalle)<span class="block text-xs text-amber-800/80">{{ \Illuminate\Support\Str::limit($a->detalle, 180) }}</span>@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Línea de tiempo --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Historial del equipo ({{ $equipo->eventos->count() }})</h3>
                    <span class="text-xs text-slate-500">Se completa solo con incidencias, atenciones y hojas de servicio</span></div>
                <div class="card-body">
                    @forelse ($equipo->eventos as $ev)
                        <div class="relative flex gap-4 pb-6 last:pb-0">
                            @unless ($loop->last)<span class="absolute left-4 top-9 -ml-px h-full w-0.5 bg-slate-200"></span>@endunless
                            <div class="relative flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white
                                {{ ['red' => 'bg-red-100 text-red-600', 'amber' => 'bg-amber-100 text-amber-700', 'green' => 'bg-emerald-100 text-emerald-700', 'blue' => 'bg-blue-100 text-blue-700', 'violet' => 'bg-violet-100 text-violet-700', 'slate' => 'bg-slate-100 text-slate-600'][$ev->tipoColor()] }}">
                                <x-icon :name="$ev->tipoIcono()" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-badge :color="$ev->tipoColor()" :dot="false">{{ $ev->tipoLabel() }}</x-badge>
                                    <span class="text-xs tabular-nums text-slate-500">{{ $ev->fecha->format('d/m/Y H:i') }} · {{ $ev->usuario?->name ?? 'Sistema' }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-slate-800">{{ $ev->titulo }}</p>
                                @if ($ev->componente)<p class="text-xs text-violet-700">Componente: {{ $ev->componente }}</p>@endif
                                @if ($ev->detalle)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $ev->detalle }}</p>@endif
                                <div class="mt-1 flex flex-wrap gap-3 text-xs">
                                    @if ($ev->incidencia)<a href="{{ route('incidencias.show', $ev->incidencia) }}" class="text-brand-600 hover:underline">Incidencia {{ $ev->incidencia->codigo }}</a>@endif
                                    @if ($ev->hojaServicio)<a href="{{ route('hojas.show', $ev->hojaServicio) }}" class="text-brand-600 hover:underline">Hoja {{ $ev->hojaServicio->numero }}</a>@endif
                                    @if ($ev->estado_equipo)<span class="text-slate-500">Quedó: {{ \App\Models\Equipo::ESTADOS[$ev->estado_equipo] ?? $ev->estado_equipo }}</span>@endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-empty icon="history" text="Aún no hay registros en el historial." />
                    @endforelse
                </div>
            </div>

            @if ($equipo->incidencias->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Incidencias del equipo ({{ $equipo->incidencias->count() }})</h3></div>
                    @foreach ($equipo->incidencias as $i)
                        <a href="{{ route('incidencias.show', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $i->codigo }} · {{ $i->titulo }}</p>
                                <p class="text-xs text-slate-500">{{ $i->fecha_reporte->format('d/m/Y') }} · {{ $i->tecnico?->name ?? 'Sin asignar' }} · {{ $i->coberturaLabel() }}</p>
                            </div>
                            <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Ficha --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ficha del equipo</h3></div>
                <div class="card-body">
                    <div class="mb-4 flex items-center gap-4">
                        <img src="{{ \App\Support\Qr::dataUri($equipo->contenidoQr(), 180) }}" alt="QR {{ $equipo->codigo }}" class="size-24 rounded border border-slate-200">
                        <div>
                            <p class="font-mono text-lg font-bold text-slate-900">{{ $equipo->codigo }}</p>
                            <p class="text-xs text-slate-500">Escanee el sticker con la app o la cámara del celular para abrir este historial.</p>
                        </div>
                    </div>
                    <dl class="dl-grid sm:grid-cols-1">
                        <div><dt>Categoría</dt><dd>{{ $equipo->categoria }}</dd></div>
                        <div><dt>Marca / modelo</dt><dd>{{ trim($equipo->marca.' '.$equipo->modelo) ?: '—' }}</dd></div>
                        <div><dt>N° de serie</dt><dd class="font-mono">{{ $equipo->serie ?? '—' }}</dd></div>
                        @if ($equipo->descripcion)<div><dt>Descripción</dt><dd>{{ $equipo->descripcion }}</dd></div>@endif
                        <div><dt>Cliente</dt><dd><a href="{{ route('entidades.show', $equipo->entidad) }}" class="text-brand-600 hover:underline">{{ $equipo->entidad->nombre }}</a></dd></div>
                        <div><dt>Proyecto / servicio</dt><dd>
                            @if ($equipo->proyecto)<a href="{{ route('proyectos.show', $equipo->proyecto) }}" class="text-brand-600 hover:underline">{{ $equipo->proyecto->nombre }}</a>
                                @if ($equipo->proyecto->clienteFinal)<span class="block text-xs text-slate-500">Contratado por {{ $equipo->proyecto->entidad->nombre }}</span>@endif
                            @else — @endif</dd></div>
                        <div><dt>Ubicación</dt><dd>{{ collect([$equipo->area, $equipo->ubicacion])->filter()->implode(' · ') ?: '—' }}</dd></div>
                        <div><dt>Responsable / usuario</dt><dd>{{ $equipo->responsable ?? '—' }}</dd></div>
                        <div><dt>Instalación / entrega</dt><dd>{{ $equipo->fecha_instalacion?->format('d/m/Y') ?? '—' }}</dd></div>
                        @if ($equipo->observaciones)<div><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $equipo->observaciones }}</dd></div>@endif
                    </dl>
                </div>
            </div>

            @can('equipos.gestionar')
                <div class="card" x-data="{ tipo: 'diagnostico' }">
                    <div class="card-header"><h3 class="card-title">Agregar al historial</h3></div>
                    <form method="POST" action="{{ route('equipos.eventos.store', $equipo) }}" class="card-body space-y-3">
                        @csrf
                        <x-select name="tipo" label="Tipo" :options="$tiposEvento" :placeholder="false" x-model="tipo" />
                        <x-input name="titulo" label="Resumen" required placeholder="Ej. Se cambió el disco duro por SSD 512 GB" />
                        <div x-show="tipo === 'cambio_componente'" x-cloak><x-input name="componente" label="Componente" placeholder="Disco duro, memoria RAM, fuente…" /></div>
                        <x-textarea name="detalle" label="Detalle" rows="3" placeholder="Qué se encontró, qué se hizo, en qué estado se entrega…" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-input name="fecha" type="datetime-local" label="Fecha" :value="now()" required />
                            <x-select name="estado_equipo" label="Estado del equipo" :options="\App\Models\Equipo::ESTADOS" placeholder="Sin cambio" />
                        </div>
                        <button class="btn btn-primary w-full"><x-icon name="plus" class="size-4" /> Registrar</button>
                    </form>
                </div>
            @endcan

            @if ($equipo->cotizaciones->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Cotizaciones</h3></div>
                    @foreach ($equipo->cotizaciones as $c)
                        <a href="{{ route('cotizaciones.show', $c) }}" class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-2 text-sm last:border-0 hover:bg-slate-50">
                            <span>{{ $c->numero }} · {{ $c->monto($c->total) }}</span>
                            <x-badge :color="$c->estadoColor()" :dot="false">{{ $c->estadoLabel() }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif

            <x-archivos :archivos="$equipo->archivos" tipo="equipo" :id="$equipo->id" categoria="foto"
                        :puede-subir="auth()->user()->can('equipos.gestionar')" titulo="Fotos y documentos del equipo" />
        </div>
    </div>
</x-layouts.app>
