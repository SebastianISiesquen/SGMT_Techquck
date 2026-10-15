<x-layouts.app :title="$hoja->numero">
    <x-page-header :title="'Hoja de servicio '.$hoja->numero" :subtitle="$hoja->tipo_servicio->label().' · '.$hoja->proyecto->entidad->nombre" :back="route('hojas.index')">
        <x-slot:actions>
            <a href="{{ route('hojas.pdf', $hoja) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> PDF</a>
            @can('update', $hoja)
                <a href="{{ route('hojas.edit', $hoja) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="-mt-4 mb-6 flex flex-wrap items-center gap-2">
        <x-badge :color="$hoja->estado->color()">{{ $hoja->estado->label() }}</x-badge>
        @if ($hoja->estaFirmada())
            <x-badge :color="$hoja->conforme ? 'green' : 'amber'">{{ $hoja->conforme ? 'Con conformidad' : 'Con observaciones' }}</x-badge>
        @endif
        <span class="text-xs text-slate-500">Registrada desde {{ $hoja->origen === 'movil' ? 'la app móvil' : 'la web' }}</span>
    </div>

    @if ($hoja->estado->value === 'anulada')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Anulada: {{ $hoja->motivo_anulacion }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del servicio</h3></div>
                <dl class="card-body dl-grid">
                    <div><dt>Entidad</dt><dd>{{ $hoja->proyecto->entidad->nombre }}</dd></div>
                    <div><dt>Proyecto</dt><dd><a href="{{ route('proyectos.show', $hoja->proyecto) }}" class="text-brand-600 hover:underline">{{ $hoja->proyecto->nombre }}</a></dd></div>
                    <div><dt>Garantía</dt><dd>{{ $hoja->garantia?->etiqueta() ?? '—' }}</dd></div>
                    <div><dt>Incidencia</dt><dd>
                        @if ($hoja->incidencia)<a href="{{ route('incidencias.show', $hoja->incidencia) }}" class="text-brand-600 hover:underline">{{ $hoja->incidencia->codigo }}</a>@else — @endif
                    </dd></div>
                    <div><dt>Técnico</dt><dd>{{ $hoja->tecnico?->name }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $hoja->fecha->format('d/m/Y') }}</dd></div>
                    <div><dt>Horario</dt><dd>{{ substr($hoja->hora_llegada, 0, 5) }} – {{ $hoja->hora_salida ? substr($hoja->hora_salida, 0, 5) : '…' }}
                        @if ($hoja->duracion())<span class="text-xs text-slate-500">({{ $hoja->duracion() }})</span>@endif</dd></div>
                    @if ($hoja->diagnostico)<div class="sm:col-span-2"><dt>Diagnóstico</dt><dd class="whitespace-pre-line">{{ $hoja->diagnostico }}</dd></div>@endif
                    <div class="sm:col-span-2"><dt>Trabajo realizado</dt><dd class="whitespace-pre-line">{{ $hoja->trabajo_realizado }}</dd></div>
                    @if ($hoja->resultado)<div><dt>Resultado</dt><dd>{{ $hoja->resultadoLabel() }}</dd></div>@endif
                    @if ($hoja->observaciones)<div><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $hoja->observaciones }}</dd></div>@endif
                    @if ($hoja->recomendaciones)<div><dt>Recomendaciones</dt><dd class="whitespace-pre-line">{{ $hoja->recomendaciones }}</dd></div>@endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Equipos intervenidos ({{ $hoja->equipos->count() }})</h3></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Equipo</th><th>Marca / Modelo</th><th>Serie</th><th>Acción</th><th>Estado final</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse ($hoja->equipos as $e)
                            <tr>
                                <td>{{ $e->equipo }}<p class="text-xs text-slate-500">{{ $e->ubicacion }}</p></td>
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

            <x-archivos :archivos="$hoja->archivos" tipo="hoja_servicio" :id="$hoja->id" categoria="foto"
                        :puede-subir="$hoja->estado->value !== 'anulada' && ($hoja->tecnico_id === auth()->id() || auth()->user()->can('hojas.ver'))"
                        titulo="Fotos y documentos del servicio" />
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Conformidad</h3></div>
                <div class="card-body">
                    @if ($hoja->estaFirmada())
                        <p class="text-sm"><strong>{{ $hoja->receptor_nombre }}</strong></p>
                        <p class="text-xs text-slate-500">{{ $hoja->receptor_cargo }}{{ $hoja->receptor_dni ? ' · DNI '.$hoja->receptor_dni : '' }}</p>
                        <img src="{{ \App\Services\FirmaService::base64($hoja->firma_receptor) }}" alt="Firma del responsable" class="mt-3 w-full rounded border border-slate-200">
                        @if ($hoja->firma_tecnico)
                            <p class="mt-4 text-xs font-semibold uppercase text-slate-500">Firma del técnico</p>
                            <img src="{{ \App\Services\FirmaService::base64($hoja->firma_tecnico) }}" alt="Firma del técnico" class="mt-1 w-full rounded border border-slate-200">
                        @endif
                        <p class="mt-3 text-xs text-slate-500">Firmada el {{ $hoja->fecha_firma->format('d/m/Y H:i') }}</p>
                    @elseif ($hoja->esEditable())
                        @can('update', $hoja)
                            <form method="POST" action="{{ route('hojas.firmar', $hoja) }}" class="space-y-3"
                                  data-confirmar="¿Registrar la firma de la hoja {{ $hoja->numero }}?" data-confirmar-detalle="Una vez firmada, la hoja ya no se puede modificar." data-confirmar-tipo="info" data-confirmar-boton="Registrar firma">
                                @csrf
                                <x-input name="receptor_nombre" label="Nombre de quien firma" :value="$hoja->receptor_nombre" required />
                                <div class="grid grid-cols-2 gap-2">
                                    <x-input name="receptor_dni" label="DNI / carné de extranjería" :value="$hoja->receptor_dni" pattern="([0-9]{8}|[A-Za-z0-9]{9,12})" maxlength="12" data-mensaje="Ingrese un DNI de 8 dígitos o un carné de extranjería válido." />
                                    <x-input name="receptor_cargo" label="Cargo" :value="$hoja->receptor_cargo" />
                                </div>
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="conforme" value="1" checked class="mt-0.5 rounded border-slate-300 text-emerald-600"> Da conformidad al servicio
                                </label>
                                <x-firma-pad name="firma_receptor" label="Firma del responsable" requerido />
                                <x-firma-pad name="firma_tecnico" label="Firma del técnico" />
                                <button class="btn btn-success w-full"><x-icon name="check" class="size-4" /> Registrar firma</button>
                            </form>
                        @else
                            <p class="text-sm text-amber-700">Pendiente de firma del responsable del cliente.</p>
                        @endcan
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
