<x-layouts.app :title="$proyecto->nombre">
    <x-page-header :title="$proyecto->nombre" :subtitle="$proyecto->codigo.' · '.$proyecto->claseLabel().' · '.$proyecto->clientes()" :back="route('proyectos.index')">
        <x-slot:actions>
            @can('proyectos.gestionar')
                <a href="{{ route('proyectos.edit', $proyecto) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
            @endcan
            @can('mantenimientos.gestionar')
                <a href="{{ route('planes.create', ['proyecto_id' => $proyecto->id]) }}" class="btn btn-secondary"><x-icon name="calendar" class="size-4" /> Programar mantenimientos</a>
            @endcan
            @can('incidencias.gestionar')
                <a href="{{ route('incidencias.create', ['proyecto_id' => $proyecto->id]) }}" class="btn btn-secondary"><x-icon name="alert" class="size-4" /> Registrar incidencia</a>
            @endcan
            @can('garantias.gestionar')
                <a href="{{ route('garantias.create', ['proyecto_id' => $proyecto->id]) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva garantía</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ficha del {{ $proyecto->esServicio() ? 'servicio' : 'proyecto' }}</h3>
                    <x-badge :color="$proyecto->estado === 'operativo' ? 'green' : 'slate'">{{ $proyecto->estadoLabel() }}</x-badge></div>
                <dl class="card-body dl-grid">
                    <div><dt>Cliente (contratante)</dt><dd><a class="text-brand-600 hover:underline" href="{{ route('entidades.show', $proyecto->entidad) }}">{{ $proyecto->entidad->nombre }}</a>
                        <span class="block text-xs text-slate-500">{{ $proyecto->entidad->naturalezaLabel() }} · {{ $proyecto->entidad->sector }}</span></dd></div>
                    <div><dt>Cliente final</dt><dd>
                        @if ($proyecto->clienteFinal)
                            <a class="text-brand-600 hover:underline" href="{{ route('entidades.show', $proyecto->clienteFinal) }}">{{ $proyecto->clienteFinal->nombre }}</a>
                            <span class="block text-xs text-slate-500">Subcontrato: el servicio se presta a este cliente</span>
                        @else — (contrato directo) @endif
                    </dd></div>
                    <div><dt>Tipo</dt><dd>{{ $proyecto->claseLabel() }} · {{ $proyecto->tipo }}</dd></div>
                    <div><dt>Orden de compra</dt><dd>{{ $proyecto->orden_compra ?? '—' }}</dd></div>
                    <div><dt>N° contrato</dt><dd>{{ $proyecto->numero_contrato ?? '—' }}</dd></div>
                    <div><dt>Monto</dt><dd>{{ $proyecto->monto_contrato ? 'S/ '.number_format($proyecto->monto_contrato, 2) : '—' }}</dd></div>
                    <div><dt>Inicio de ejecución</dt><dd>{{ $proyecto->fecha_inicio?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>{{ $proyecto->esServicio() ? 'Inicio del servicio' : 'Entrega / conformidad' }}</dt><dd>{{ $proyecto->fecha_entrega->format('d/m/Y') }} <span class="text-xs text-slate-500">({{ $proyecto->fecha_entrega->diffForHumans() }})</span></dd></div>
                    @if ($proyecto->esServicio())
                        <div><dt>Fin del contrato</dt><dd>{{ $proyecto->fecha_fin?->format('d/m/Y') ?? 'Sin fecha de término' }}</dd></div>
                    @endif
                    <div><dt>Ubicación</dt><dd>{{ $proyecto->ubicacion ?? '—' }}</dd></div>
                    <div><dt>Responsable</dt><dd>{{ $proyecto->responsable?->name ?? '—' }}</dd></div>
                    @if ($proyecto->descripcion)
                        <div class="sm:col-span-2"><dt>Descripción</dt><dd class="whitespace-pre-line">{{ $proyecto->descripcion }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Garantías ({{ $proyecto->garantias->count() }})</h3></div>
                @forelse ($proyecto->garantias as $g)
                    <a href="{{ route('garantias.show', $g) }}" class="block border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium text-slate-800">{{ $g->tipo }} <span class="text-xs font-normal text-slate-500">· {{ $g->codigo }} · {{ $g->duracionTexto() }}</span></p>
                            <x-badge :color="$g->estado->color()">{{ $g->estado->label() }}</x-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $g->fecha_inicio->format('d/m/Y') }} → {{ $g->fecha_vencimiento->format('d/m/Y') }}
                            @if ($g->diasRestantes() >= 0) · quedan {{ $g->diasRestantes() }} días @endif</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ ['green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'slate' => 'bg-slate-400'][$g->estado->color()] }}" style="width: {{ $g->avance() }}%"></div>
                        </div>
                    </a>
                @empty
                    <x-empty icon="shield" text="Aún no tiene garantías registradas." />
                @endforelse
            </div>

            <div class="card" x-data="{ agregar: {{ $errors->has('equipos.*') ? 'true' : 'false' }} }">
                <div class="card-header">
                    <h3 class="card-title">Equipos {{ $proyecto->esServicio() ? 'del servicio' : 'instalados' }} ({{ $proyecto->equipos->count() }})
                        @if ($proyecto->equipos_con_falla_count)<x-badge color="red" class="ml-1">{{ $proyecto->equipos_con_falla_count }} con falla</x-badge>@endif
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @if ($proyecto->equipos->isNotEmpty())
                            <a href="{{ route('equipos.etiquetas', ['proyecto_id' => $proyecto->id]) }}" target="_blank" class="btn btn-ghost btn-sm"><x-icon name="qr" class="size-4" /> Stickers</a>
                        @endif
                        @can('proyectos.gestionar')
                            <a href="{{ route('equipos.importar', ['entidad_id' => $proyecto->lugarDeServicio()->id, 'proyecto_id' => $proyecto->id]) }}" class="btn btn-ghost btn-sm"><x-icon name="upload" class="size-4" /> Excel</a>
                            <button type="button" class="btn btn-secondary btn-sm" @click="agregar = !agregar"><x-icon name="plus" class="size-4" /> Agregar equipos</button>
                        @endcan
                    </div>
                </div>

                @can('proyectos.gestionar')
                    <form x-show="agregar" x-cloak method="POST" action="{{ route('proyectos.equipos.store', $proyecto) }}" class="border-b border-slate-100 bg-slate-50/60 p-4">
                        @csrf
                        @include('proyectos._equipos-filas', ['iniciales' => [['categoria' => '', 'marca' => '', 'modelo' => '', 'serie' => '', 'cantidad' => 1, 'ubicacion' => '', 'area' => '']]])
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" @click="agregar = false">Cancelar</button>
                            <button class="btn btn-primary btn-sm">Guardar equipos</button>
                        </div>
                    </form>
                @endcan

                @if ($proyecto->equipos->isNotEmpty())
                    @php
                        $editable = auth()->user()->can('proyectos.gestionar');
                        $subeFei = auth()->user()->can('equipos.gestionar');
                    @endphp
                    <div x-data="{ cambios: {{ $errors->has('editar.*') ? 'true' : 'false' }} }">
                    @if ($editable)
                        {{-- Los campos de la tabla apuntan a este formulario (atributo form=), así cada fila puede tener su propio formulario de foto --}}
                        <form id="form-editar-equipos" method="POST" action="{{ route('proyectos.equipos.update', $proyecto) }}">@csrf @method('PUT')</form>
                        @foreach ($errors->get('editar.*') as $mensajes)
                            @foreach ($mensajes as $m)<p class="error px-4 pt-2">{{ $m }}</p>@endforeach
                        @endforeach
                    @endif
                    <div class="max-h-[28rem] overflow-auto">
                        <table class="table">
                            <thead class="sticky top-0 z-10"><tr><th>Equipo</th><th>Serie</th><th>Ubicación</th><th>Estado</th><th title="Foto del equipo instalado">FEI</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach ($proyecto->equipos as $e)
                                <tr>
                                    <td><a href="{{ route('equipos.show', $e) }}" class="font-medium text-slate-800 hover:text-brand-600">{{ $e->categoria }}</a>
                                        @if ($e->incidencias_count)<x-badge color="amber" :dot="false" class="ml-1">{{ $e->incidencias_count }} falla(s)</x-badge>@endif
                                        <p class="text-xs text-slate-500"><span class="font-mono text-brand-600">{{ $e->codigo }}</span>{{ trim($e->marca.' '.$e->modelo) ? ' · '.trim($e->marca.' '.$e->modelo) : '' }}</p></td>
                                    @if ($editable)
                                        <td><input form="form-editar-equipos" name="editar[{{ $e->id }}][serie]" value="{{ old("editar.{$e->id}.serie", $e->serie) }}"
                                                   placeholder="N° serie" maxlength="80" @input="cambios = true"
                                                   class="input min-w-32 py-1 font-mono text-xs @error("editar.{$e->id}.serie") border-red-400 @enderror"></td>
                                        <td><input form="form-editar-equipos" name="editar[{{ $e->id }}][ubicacion]" value="{{ old("editar.{$e->id}.ubicacion", $e->ubicacion) }}"
                                                   placeholder="Ubicación" maxlength="255" @input="cambios = true" class="input min-w-40 py-1 text-xs">
                                            @if ($e->area)<p class="mt-0.5 text-[11px] text-slate-400">{{ $e->area }}</p>@endif</td>
                                    @else
                                        <td class="whitespace-nowrap font-mono text-xs">{{ $e->serie ?? '—' }}</td>
                                        <td class="text-xs">{{ collect([$e->area, $e->ubicacion])->filter()->implode(' · ') ?: '—' }}</td>
                                    @endif
                                    <td><x-badge :color="$e->estadoColor()">{{ $e->estadoLabel() }}</x-badge></td>
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            @if ($e->fotoInstalacion)
                                                <a href="{{ route('archivos.ver', $e->fotoInstalacion) }}" target="_blank" title="Ver foto del equipo instalado">
                                                    <img src="{{ route('archivos.ver', $e->fotoInstalacion) }}" alt="FEI {{ $e->codigo }}" loading="lazy" class="size-10 rounded-md border border-slate-200 object-cover">
                                                </a>
                                            @endif
                                            @if ($subeFei)
                                                <form method="POST" action="{{ route('archivos.store', ['tipo' => 'equipo', 'id' => $e->id]) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="categoria" value="foto_instalacion">
                                                    <input type="hidden" name="descripcion" value="FEI — foto del equipo instalado">
                                                    <label class="btn btn-ghost btn-sm cursor-pointer px-2" title="{{ $e->fotoInstalacion ? 'Cambiar' : 'Subir' }} foto del equipo instalado">
                                                        <x-icon name="camera" class="size-4" />@unless ($e->fotoInstalacion)<span class="text-xs">Subir</span>@endunless
                                                        <input type="file" name="archivos[]" accept="image/*" class="hidden" onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            @elseif (! $e->fotoInstalacion)
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($editable)
                        <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-2">
                            <span class="text-xs text-amber-700" x-show="cambios" x-cloak>Hay cambios sin guardar</span>
                            <button form="form-editar-equipos" class="btn btn-primary btn-sm" :disabled="!cambios"><x-icon name="check" class="size-4" /> Guardar serie y ubicación</button>
                        </div>
                    @endif
                    </div>
                @else
                    <x-empty icon="monitor" text="Aún no se registraron los equipos de este proyecto. Regístrelos para poder reportar fallas y llevar su historial." />
                @endif
            </div>

            @if ($proyecto->planesMantenimiento->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Mantenimientos programados</h3>
                        @can('mantenimientos.ver')<a href="{{ route('mantenimientos.index', ['proyecto_id' => $proyecto->id]) }}" class="text-xs font-medium text-brand-600 hover:underline">Ver calendario</a>@endcan</div>
                    @foreach ($proyecto->planesMantenimiento as $plan)
                        <div class="border-b border-slate-100 px-5 py-4 last:border-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ route('planes.show', $plan) }}" class="text-sm font-medium text-slate-800 hover:text-brand-600">{{ $plan->nombre }}</a>
                                <span class="text-xs text-slate-500">{{ $plan->realizadas() }} de {{ $plan->total_visitas }} realizados · cada {{ $plan->frecuencia_meses }} mes(es)</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($plan->mantenimientos as $m)
                                    <x-badge :color="$m->estadoColor()" :dot="false" title="{{ $m->estadoLabel() }}">{{ $m->numero }}/{{ $plan->total_visitas }} · {{ $m->fecha_programada->format('d/m/Y') }}</x-badge>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Últimas incidencias</h3>
                    @can('incidencias.ver')<a href="{{ route('incidencias.index', ['proyecto_id' => $proyecto->id]) }}" class="text-xs font-medium text-brand-600 hover:underline">Ver todas</a>@endcan</div>
                @forelse ($proyecto->incidencias as $i)
                    <a href="{{ route('incidencias.show', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $i->codigo }} · {{ $i->titulo }}</p>
                            <p class="text-xs text-slate-500">{{ $i->fecha_reporte->format('d/m/Y') }} · {{ $i->tecnico?->name ?? 'Sin asignar' }}</p>
                        </div>
                        <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                    </a>
                @empty
                    <x-empty icon="check-circle" text="Sin incidencias registradas." />
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="card" x-data="{ mes: '{{ now()->format('Y-m') }}' }">
                <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="file" class="size-4" /> Informe al cliente</h3>
                    @if ($proyecto->informe_mensual)<x-badge color="green" :dot="false">Envío mensual activo</x-badge>@endif</div>
                <div class="card-body space-y-3 text-sm">
                    @if ($proyecto->esServicio())
                        <p class="text-slate-600">Mantenimientos, incidencias atendidas, estado de los equipos y vigencia de las garantías del periodo.</p>
                        <div>
                            <label class="label" for="f_mes">Mes</label>
                            <input id="f_mes" type="month" x-model="mes" max="{{ now()->format('Y-m') }}" class="input">
                        </div>
                    @else
                        <p class="text-slate-600">Todo el proyecto, desde el inicio de ejecución ({{ $proyecto->fecha_inicio?->format('d/m/Y') ?? 'sin registrar' }}) y la entrega / conformidad ({{ $proyecto->fecha_entrega->format('d/m/Y') }}): equipos instalados con su FEI, servicios, incidencias y garantías.</p>
                    @endif
                    <a :href="'{{ route('proyectos.informe', $proyecto) }}?mes=' + mes" target="_blank" class="btn btn-secondary w-full"><x-icon name="pdf" class="size-4" /> Ver informe (PDF)</a>
                    @can('proyectos.gestionar')
                        <form method="POST" action="{{ route('proyectos.informe.enviar', $proyecto) }}" data-confirmar="¿Enviar el informe por correo al cliente?" data-confirmar-tipo="info" data-confirmar-boton="Enviar">
                            @csrf
                            <input type="hidden" name="mes" :value="mes">
                            <button class="btn btn-primary w-full"><x-icon name="send" class="size-4" /> Enviar al cliente</button>
                        </form>
                        <p class="text-xs text-slate-500">Se envía a: {{ implode(', ', $proyecto->entidad->correosAlerta()) ?: 'el cliente no tiene correos registrados' }}</p>
                    @endcan
                </div>
            </div>

            <x-archivos :archivos="$proyecto->archivos" tipo="proyecto" :id="$proyecto->id" categoria="contrato"
                        :puede-subir="auth()->user()->can('proyectos.gestionar')" titulo="Documentación" />
        </div>
    </div>
</x-layouts.app>
