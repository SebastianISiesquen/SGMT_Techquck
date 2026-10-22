<x-layouts.app :title="$incidencia->codigo">
    <x-page-header :title="$incidencia->titulo" :back="route('incidencias.index')">
        <x-slot:actions>
            <a href="{{ route('incidencias.informe', $incidencia) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> Informe PDF</a>
            @can('hojas.registrar')
                @if (in_array($incidencia->estado->value, ['asignada', 'en_proceso', 'resuelta']))
                    <a href="{{ route('hojas.create', ['incidencia_id' => $incidencia->id]) }}" class="btn btn-secondary"><x-icon name="clipboard" class="size-4" /> Hoja de servicio</a>
                @endif
            @endcan
            @can('cotizaciones.gestionar')
                @if ($incidencia->cobertura === 'sin_cobertura')
                    <a href="{{ route('cotizaciones.create', ['incidencia_id' => $incidencia->id]) }}" class="btn btn-secondary"><x-icon name="receipt" class="size-4" /> Cotizar</a>
                @endif
            @endcan
            @can('incidencias.gestionar')
                @if ($incidencia->estaAbierta())
                    <a href="{{ route('incidencias.edit', $incidencia) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="-mt-4 mb-6 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-mono text-slate-500">{{ $incidencia->codigo }}</span>
        <x-badge :color="$incidencia->estado->color()">{{ $incidencia->estado->label() }}</x-badge>
        <x-badge :color="$incidencia->prioridad->color()">Prioridad {{ $incidencia->prioridad->label() }}</x-badge>
        @if ($incidencia->estaVencidaSla())<x-badge color="red">Fuera de plazo</x-badge>@endif
        <x-badge :color="['garantia' => 'green', 'contrato' => 'blue', 'sin_cobertura' => 'amber'][$incidencia->cobertura] ?? 'slate'" :dot="false">{{ $incidencia->coberturaLabel() }}</x-badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Acciones según estado --}}
            @if ($incidencia->estado->value === 'resuelta')
                @can('incidencias.cerrar')
                    <div class="card border-violet-200 bg-violet-50/50 p-5" x-data="{ reabrir: false }">
                        <p class="flex items-center gap-2 text-sm font-semibold text-violet-900"><x-icon name="check-circle" class="size-5" /> El técnico reportó la incidencia como resuelta</p>
                        <p class="mt-1 text-sm text-violet-800">Revise la atención y las evidencias.
                            @if ($requiereActa)
                                {!! $incidencia->tieneActaConformidad()
                                    ? '<span class="font-medium text-emerald-700">✓ Acta de conformidad firmada recibida.</span>'
                                    : '<span class="font-medium text-red-700">Falta subir el acta de conformidad firmada por el cliente (en la hoja de servicio) para poder cerrar.</span>' !!}
                            @endif
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2" x-show="!reabrir">
                            <form method="POST" action="{{ route('incidencias.cerrar', $incidencia) }}" class="flex flex-1 flex-wrap gap-2"
                                  data-confirmar="¿Cerrar la incidencia {{ $incidencia->codigo }}?" data-confirmar-detalle="Se registra la conformidad del servicio. Si después el problema persiste, podrá reabrirla."
                                  data-confirmar-tipo="info" data-confirmar-boton="Cerrar incidencia">
                                @csrf
                                <input name="comentario" class="input min-w-48 flex-1" placeholder="Comentario de cierre (opcional)">
                                <button class="btn btn-success"><x-icon name="check" class="size-4" /> Dar conformidad y cerrar</button>
                            </form>
                            <button type="button" class="btn btn-secondary" @click="reabrir = true"><x-icon name="refresh" class="size-4" /> Devolver al técnico</button>
                        </div>
                        <form method="POST" action="{{ route('incidencias.reabrir', $incidencia) }}" x-show="reabrir" x-cloak class="mt-4 flex flex-wrap gap-2"
                              data-confirmar="¿Devolver la incidencia al técnico?" data-confirmar-detalle="Volverá a estado «En proceso» y se le notificará al técnico." data-confirmar-tipo="advertencia" data-confirmar-boton="Devolver">
                            @csrf
                            <input name="motivo" required class="input min-w-48 flex-1" placeholder="Motivo (ej. falta evidencia, el problema persiste)">
                            <button class="btn btn-primary">Reabrir</button>
                            <button type="button" class="btn btn-ghost" @click="reabrir = false">Cancelar</button>
                        </form>
                    </div>
                @endcan
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Detalle de la incidencia</h3></div>
                <div class="card-body space-y-5">
                    <p class="text-sm whitespace-pre-line text-slate-700">{{ $incidencia->descripcion }}</p>
                    <dl class="dl-grid">
                        <div><dt>Proyecto / servicio</dt><dd><a href="{{ route('proyectos.show', $incidencia->proyecto) }}" class="text-brand-600 hover:underline">{{ $incidencia->proyecto->nombre }}</a></dd></div>
                        <div><dt>Cliente</dt><dd>{{ $incidencia->proyecto->entidad->nombre }}
                            @if ($incidencia->proyecto->clienteFinal)<span class="block text-xs text-slate-500">Cliente final: {{ $incidencia->proyecto->clienteFinal->nombre }}</span>@endif</dd></div>
                        <div><dt>Garantía</dt><dd>
                            @if ($incidencia->garantia)
                                <a href="{{ route('garantias.show', $incidencia->garantia) }}" class="text-brand-600 hover:underline">{{ $incidencia->garantia->etiqueta() }}</a>
                                <x-badge :color="$incidencia->garantia->estado->color()" class="ml-1">{{ $incidencia->garantia->estado->label() }}</x-badge>
                            @else — @endif
                        </dd></div>
                        <div><dt>Ubicación / equipo</dt><dd>{{ $incidencia->ubicacion_detalle ?? '—' }}</dd></div>
                        <div><dt>Reportado por</dt><dd>{{ $incidencia->reportado_por ?? '—' }}{{ $incidencia->canal ? ' · '.$incidencia->canal : '' }}</dd></div>
                        <div><dt>Fecha de reporte</dt><dd>{{ $incidencia->fecha_reporte->format('d/m/Y H:i') }}</dd></div>
                        <div><dt>Fecha límite</dt><dd>{{ $incidencia->fecha_limite?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                        <div><dt>Registrado por</dt><dd>{{ $incidencia->registrador?->name ?? '—' }}</dd></div>
                        @if ($incidencia->fecha_resolucion)
                            <div><dt>Resuelta</dt><dd>{{ $incidencia->fecha_resolucion->format('d/m/Y H:i') }}
                                <span class="text-xs text-slate-500">({{ $incidencia->fecha_reporte->diffForHumans($incidencia->fecha_resolucion, true) }} desde el reporte)</span></dd></div>
                        @endif
                        @if ($incidencia->fecha_cierre)
                            <div><dt>{{ $incidencia->estado->value === 'anulada' ? 'Anulada' : 'Cerrada' }}</dt><dd>{{ $incidencia->fecha_cierre->format('d/m/Y H:i') }} · {{ $incidencia->cerrador?->name }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>

            @include('incidencias._equipo')
            @include('incidencias._atenciones')
            @include('incidencias._hojas')

            @if ($incidencia->cotizaciones->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Cotizaciones</h3></div>
                    @foreach ($incidencia->cotizaciones as $c)
                        <a href="{{ route('cotizaciones.show', $c) }}" class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                            <span>{{ $c->numero }} · {{ $c->asunto }}</span>
                            <span class="flex items-center gap-2 tabular-nums">{{ $c->monto($c->total) }} <x-badge :color="$c->estadoColor()" :dot="false">{{ $c->estadoLabel() }}</x-badge></span>
                        </a>
                    @endforeach
                </div>
            @endif

            <x-archivos :archivos="$incidencia->archivos" tipo="incidencia" :id="$incidencia->id"
                        :puede-subir="auth()->user()->can('adjuntar', $incidencia)" titulo="Documentos de la incidencia" />
        </div>

        <div class="space-y-6">
            {{-- Asignación (RF08) --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Técnico responsable</h3></div>
                <div class="card-body">
                    @if ($incidencia->tecnico)
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{{ $incidencia->tecnico->iniciales() }}</span>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $incidencia->tecnico->name }}</p>
                                <p class="text-xs text-slate-500">Asignada {{ $incidencia->fecha_asignacion?->format('d/m/Y H:i') }} por {{ $incidencia->asignador?->name }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Sin técnico asignado.</p>
                    @endif

                    @can('incidencias.asignar')
                        @if ($incidencia->estaAbierta())
                            <form method="POST" action="{{ route('incidencias.asignar', $incidencia) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                @csrf
                                <div>
                                    <label class="label" for="f_tecnico">{{ $incidencia->tecnico ? 'Reasignar a' : 'Asignar a' }}</label>
                                    <select id="f_tecnico" name="tecnico_id" required class="input">
                                        <option value="">Seleccione técnico…</option>
                                        @foreach ($tecnicos as $t)
                                            <option value="{{ $t->id }}" @selected($t->id === $incidencia->tecnico_id)>{{ $t->name }} ({{ $t->carga }} abiertas)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-input name="fecha_limite" type="datetime-local" label="Fecha límite" :value="$incidencia->fecha_limite" />
                                <x-input name="comentario" label="Indicaciones (opcional)" />
                                <button class="btn btn-primary w-full"><x-icon name="send" class="size-4" /> {{ $incidencia->tecnico ? 'Reasignar' : 'Asignar' }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            @if ($incidencia->estaAbierta() && ($incidencia->proxima_visita || $incidencia->hojasServicio->isNotEmpty()))
                <div class="card">
                    <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="calendar" class="size-4 text-slate-400" /> Próxima visita</h3></div>
                    <div class="card-body space-y-3">
                        @if ($incidencia->proxima_visita)
                            <p class="text-lg font-semibold {{ $incidencia->proxima_visita->isPast() && ! $incidencia->proxima_visita->isToday() ? 'text-red-600' : 'text-slate-900' }}">{{ $incidencia->proxima_visita->format('d/m/Y') }}
                                <span class="text-xs font-normal text-slate-500">({{ $incidencia->proxima_visita->isToday() ? 'hoy' : $incidencia->proxima_visita->diffForHumans() }})</span></p>
                            @if ($incidencia->motivo_reprogramacion)<p class="text-sm text-slate-600">Pendiente: {{ $incidencia->motivo_reprogramacion }}</p>@endif
                        @else
                            <p class="text-sm text-slate-500">Sin visita programada.</p>
                        @endif
                        @can('incidencias.asignar')
                            <details>
                                <summary class="cursor-pointer text-sm font-medium text-brand-600">{{ $incidencia->proxima_visita ? 'Reprogramar' : 'Programar visita' }}</summary>
                                <form method="POST" action="{{ route('incidencias.reprogramar', $incidencia) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <x-input name="proxima_visita" type="date" label="Fecha" :value="$incidencia->proxima_visita" :min="today()->toDateString()" required />
                                    <x-input name="motivo_reprogramacion" label="Motivo / pendiente" :value="$incidencia->motivo_reprogramacion" placeholder="Ej. El cliente pidió el lunes" />
                                    <button class="btn btn-primary btn-sm w-full">Guardar y avisar al técnico</button>
                                </form>
                            </details>
                        @endcan
                    </div>
                </div>
            @endif

            @include('incidencias._historial')

            @can('incidencias.cerrar')
                @if (! in_array($incidencia->estado->value, ['cerrada', 'anulada']))
                    <details class="card">
                        <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-red-600">Anular incidencia</summary>
                        <form method="POST" action="{{ route('incidencias.anular', $incidencia) }}" class="space-y-3 px-5 pb-5" data-confirmar="¿Anular la incidencia?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, anular" data-confirmar-detalle="El registro quedará anulado y se conservará en el historial.">
                            @csrf
                            <x-textarea name="motivo" label="Motivo" required rows="2" />
                            <button class="btn btn-danger w-full"><x-icon name="ban" class="size-4" /> Anular</button>
                        </form>
                    </details>
                @elseif ($incidencia->estado->value === 'cerrada')
                    <details class="card">
                        <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-slate-600">Reabrir incidencia</summary>
                        <form method="POST" action="{{ route('incidencias.reabrir', $incidencia) }}" class="space-y-3 px-5 pb-5"
                              data-confirmar="¿Reabrir la incidencia {{ $incidencia->codigo }}?" data-confirmar-tipo="advertencia" data-confirmar-boton="Reabrir">
                            @csrf
                            <x-textarea name="motivo" label="Motivo" required rows="2" />
                            <button class="btn btn-secondary w-full"><x-icon name="refresh" class="size-4" /> Reabrir</button>
                        </form>
                    </details>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.app>
