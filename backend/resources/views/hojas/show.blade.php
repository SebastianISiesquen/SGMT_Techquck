@php
    $actas = $hoja->actas();
    $otros = $hoja->archivos->reject(fn ($a) => $a->categoria->value === 'acta_conformidad');
    $puedeSubir = $hoja->estado->value !== 'anulada' && ($hoja->tecnico_id === auth()->id() || auth()->user()->can('hojas.ver'));
    $resColor = ['resuelto' => 'green', 'parcial' => 'amber', 'pendiente_repuesto' => 'blue', 'no_procede' => 'red'];
@endphp
<x-layouts.app :title="$hoja->numero">
    <x-page-header :title="'Hoja de servicio '.$hoja->numero" :subtitle="$hoja->tipo_servicio->label().' · '.$hoja->proyecto->lugarDeServicio()->nombre.' · '.$hoja->fecha->format('d/m/Y')" :back="$hoja->incidencia ? route('incidencias.show', $hoja->incidencia) : route('hojas.index')">
        <x-slot:actions>
            <a href="{{ route('hojas.pdf', $hoja) }}" target="_blank" class="btn btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / PDF</a>
            @can('update', $hoja)
                <a href="{{ route('hojas.edit', $hoja) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="-mt-4 mb-6 flex flex-wrap items-center gap-2">
        <x-badge :color="$hoja->estado->color()">{{ $hoja->estado->label() }}</x-badge>
        @if ($hoja->resultado)<x-badge :color="$resColor[$hoja->resultado] ?? 'slate'" :dot="false">{{ $hoja->resultadoLabel() }}</x-badge>@endif
        <span class="text-xs text-slate-500">Registrada desde {{ $hoja->origen === 'movil' ? 'la app móvil' : 'la web' }} por {{ $hoja->tecnico?->name }}</span>
    </div>

    @if ($hoja->estado->value === 'anulada')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Anulada: {{ $hoja->motivo_anulacion }}</div>
    @endif

    {{-- Siguiente paso: conseguir el acta firmada --}}
    @if ($hoja->esEditable())
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <p class="flex items-center gap-2 font-semibold text-amber-900"><x-icon name="clipboard" class="size-5" /> Falta el acta de conformidad firmada</p>
            <ol class="mt-2 space-y-1 text-sm text-amber-900">
                <li>1. <a href="{{ route('hojas.pdf', $hoja) }}" target="_blank" class="font-medium underline">Imprima la hoja de servicio</a>.</li>
                <li>2. Pida al responsable del cliente que la firme y selle.</li>
                <li>3. Tome una foto o escanéela y súbala aquí.</li>
            </ol>
            @if ($puedeSubir)
                <form method="POST" action="{{ route('archivos.store', ['hoja_servicio', $hoja->id]) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="hidden" name="categoria" value="acta_conformidad">
                    <input type="hidden" name="descripcion" value="Acta de conformidad firmada">
                    <input type="file" name="archivos[]" multiple required accept="image/*,application/pdf"
                           class="block min-w-64 flex-1 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-amber-800">
                    <button class="btn btn-success"><x-icon name="upload" class="size-4" /> Subir acta firmada</button>
                </form>
                @error('archivos.*')<p class="error mt-2">{{ $message }}</p>@enderror
            @endif
        </div>
    @endif

    @if ($hoja->requiereVisita())
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <x-icon name="calendar" class="mt-0.5 size-5 shrink-0" />
            <div><p class="font-semibold">El trabajo continúa el {{ $hoja->proxima_visita?->format('d/m/Y') }}</p>
                <p>Pendiente: {{ $hoja->pendientes }}</p></div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del servicio</h3></div>
                <dl class="card-body dl-grid">
                    <div><dt>Cliente</dt><dd>{{ $hoja->proyecto->entidad->nombre }}
                        @if ($hoja->proyecto->clienteFinal)<span class="block text-xs text-slate-500">Cliente final: {{ $hoja->proyecto->clienteFinal->nombre }}</span>@endif</dd></div>
                    <div><dt>Proyecto / servicio</dt><dd><a href="{{ route('proyectos.show', $hoja->proyecto) }}" class="text-brand-600 hover:underline">{{ $hoja->proyecto->nombre }}</a></dd></div>
                    <div><dt>Incidencia</dt><dd>
                        @if ($hoja->incidencia)<a href="{{ route('incidencias.show', $hoja->incidencia) }}" class="text-brand-600 hover:underline">{{ $hoja->incidencia->codigo }} · {{ $hoja->incidencia->titulo }}</a>
                        @elseif ($hoja->mantenimiento){{ $hoja->mantenimiento->etiqueta() }}@else — @endif
                    </dd></div>
                    <div><dt>Garantía</dt><dd>{{ $hoja->garantia?->etiqueta() ?? '—' }}</dd></div>
                    <div><dt>Fecha y horario</dt><dd>{{ $hoja->fecha->format('d/m/Y') }} · {{ substr($hoja->hora_llegada, 0, 5) }} – {{ $hoja->hora_salida ? substr($hoja->hora_salida, 0, 5) : '…' }}
                        @if ($hoja->duracion())<span class="text-xs text-slate-500">({{ $hoja->duracion() }})</span>@endif</dd></div>
                    <div><dt>Recibe el servicio</dt><dd>{{ $hoja->receptor_nombre ?? '—' }}
                        <span class="block text-xs text-slate-500">{{ collect([$hoja->receptor_cargo, $hoja->receptor_dni ? 'DNI '.$hoja->receptor_dni : null])->filter()->implode(' · ') }}</span></dd></div>
                    @if ($hoja->diagnostico)<div class="sm:col-span-2"><dt>Diagnóstico</dt><dd class="whitespace-pre-line">{{ $hoja->diagnostico }}</dd></div>@endif
                    <div class="sm:col-span-2"><dt>Trabajo realizado</dt><dd class="whitespace-pre-line">{{ $hoja->trabajo_realizado }}</dd></div>
                    @if ($hoja->observaciones)<div><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $hoja->observaciones }}</dd></div>@endif
                    @if ($hoja->recomendaciones)<div><dt>Recomendaciones</dt><dd class="whitespace-pre-line">{{ $hoja->recomendaciones }}</dd></div>@endif
                </dl>
            </div>

            @if ($hoja->verificaciones || $hoja->mediciones)
                <div class="grid gap-6 md:grid-cols-2">
                    @if ($hoja->verificaciones)
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Verificaciones previas</h3></div>
                            <ul class="card-body space-y-1.5 text-sm">
                                @foreach ($hoja->verificaciones as $v)
                                    <li class="flex items-start gap-2">
                                        <span class="w-8 shrink-0 text-xs font-bold {{ ['ok' => 'text-emerald-600', 'no' => 'text-red-600'][$v['resultado']] ?? 'text-slate-400' }}">{{ ['ok' => 'Sí', 'no' => 'No', 'na' => 'N/A'][$v['resultado']] ?? '' }}</span>
                                        <span>{{ $v['item'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($hoja->mediciones)
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Mediciones</h3></div>
                            <table class="table">
                                <tbody class="divide-y divide-slate-100">
                                @foreach ($hoja->mediciones as $m)
                                    <tr><td class="text-slate-600">{{ $m['parametro'] }}</td><td class="text-right font-semibold tabular-nums">{{ $m['valor'] }} {{ $m['unidad'] }}</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Equipos intervenidos ({{ $hoja->equipos->count() }})</h3></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Equipo</th><th>Marca / Modelo</th><th>Serie</th><th>Acción</th><th>Estado final</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse ($hoja->equipos as $e)
                            <tr>
                                <td>@if ($e->inventario)<a href="{{ route('equipos.show', $e->inventario) }}" class="font-medium text-brand-600 hover:underline">{{ $e->inventario->codigo }}</a> @endif{{ $e->equipo }}
                                    <p class="text-xs text-slate-500">{{ $e->ubicacion }}</p></td>
                                <td>{{ trim($e->marca.' '.$e->modelo) ?: '—' }}</td>
                                <td class="font-mono text-xs">{{ $e->serie ?? '—' }}</td>
                                <td>{{ $e->accionLabel() }}</td>
                                <td><x-badge :color="['operativo' => 'green', 'observado' => 'amber', 'inoperativo' => 'red'][$e->estado_final] ?? 'slate'">{{ $e->estadoLabel() }}</x-badge>
                                    @if ($e->observacion)<p class="mt-1 text-xs text-slate-500">{{ $e->observacion }}</p>@endif
                                    @if ($e->checklist)
                                        @php($obs = count($e->hallazgos()))
                                        <details class="mt-1 text-xs">
                                            <summary class="cursor-pointer {{ $obs ? 'text-amber-700' : 'text-emerald-700' }}">Checklist: {{ count($e->checklist) }} puntos{{ $obs ? ", $obs con observaciones" : ', todo OK' }}</summary>
                                            <ul class="mt-1 space-y-0.5">
                                                @foreach ($e->checklist as $p)
                                                    <li><span class="{{ ['ok' => 'text-emerald-700', 'observado' => 'text-amber-700', 'falla' => 'text-red-700'][$p['resultado']] ?? 'text-slate-500' }}">{{ \App\Models\Checklist::RESULTADOS[$p['resultado']] ?? $p['resultado'] }}</span> · {{ $p['item'] }}{{ ! empty($p['nota']) ? ' — '.$p['nota'] : '' }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-slate-500">Sin equipos registrados.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($hoja->materiales->isNotEmpty())
                    <div class="border-t border-slate-100 p-5">
                        <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Materiales utilizados</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($hoja->materiales as $m)<li>{{ rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.') }} {{ $m->unidad }} — {{ $m->descripcion }}</li>@endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <x-archivos :archivos="$otros" tipo="hoja_servicio" :id="$hoja->id" categoria="foto"
                        :puede-subir="$puedeSubir" titulo="Fotos y documentos del servicio" />
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Acta de conformidad</h3>
                    <x-badge :color="$hoja->tieneActa() ? 'green' : 'amber'">{{ $hoja->tieneActa() ? 'Recibida' : 'Pendiente' }}</x-badge></div>
                <div class="card-body space-y-3">
                    @forelse ($actas as $a)
                        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <x-icon :name="$a->esImagen() ? 'camera' : 'pdf'" class="size-5 text-emerald-700" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('archivos.ver', $a) }}" target="_blank" class="block truncate text-sm font-medium text-emerald-900 hover:underline">{{ $a->nombre_original }}</a>
                                <p class="text-xs text-emerald-800">{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->autor?->name }}</p>
                            </div>
                            @can('delete', $a)
                                <form method="POST" action="{{ route('archivos.destroy', $a) }}" data-confirmar="¿Quitar esta acta?" data-confirmar-detalle="Si es la única, la hoja vuelve a quedar pendiente de acta." data-confirmar-tipo="peligro">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-600" title="Quitar"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aún no se sube el acta firmada por el cliente.</p>
                    @endforelse
                    @if ($hoja->tieneActa() && $puedeSubir)
                        <form method="POST" action="{{ route('archivos.store', ['hoja_servicio', $hoja->id]) }}" enctype="multipart/form-data" class="space-y-2 border-t border-slate-100 pt-3">
                            @csrf
                            <input type="hidden" name="categoria" value="acta_conformidad">
                            <input type="file" name="archivos[]" multiple required accept="image/*,application/pdf" class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1">
                            <button class="btn btn-secondary btn-sm w-full"><x-icon name="upload" class="size-4" /> Agregar otra página del acta</button>
                        </form>
                    @endif
                </div>
            </div>

            @can('anular', $hoja)
                <details class="card">
                    <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-red-600">Anular hoja de servicio</summary>
                    <form method="POST" action="{{ route('hojas.anular', $hoja) }}" class="space-y-3 px-5 pb-5" data-confirmar="¿Anular la hoja de servicio?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, anular" data-confirmar-detalle="El registro quedará anulado y se conservará en el historial.">
                        @csrf
                        <x-input name="motivo" label="Motivo" required />
                        <button class="btn btn-danger w-full"><x-icon name="ban" class="size-4" /> Anular</button>
                    </form>
                </details>
            @endcan
        </div>
    </div>
</x-layouts.app>
