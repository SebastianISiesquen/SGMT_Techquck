{{-- Equipos con falla, sus antecedentes (lo que otros técnicos encontraron antes) y el informe técnico de cada uno. --}}
@if ($incidencia->origen || $incidencia->derivadas->isNotEmpty())
    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        @if ($incidencia->origen)
            Estos equipos quedaron pendientes en la incidencia
            <a href="{{ route('incidencias.show', $incidencia->origen) }}" class="font-semibold underline">{{ $incidencia->origen->codigo }}</a>.
        @endif
        @foreach ($incidencia->derivadas as $d)
            <span class="block">Los equipos pendientes continúan en la incidencia
                <a href="{{ route('incidencias.show', $d) }}" class="font-semibold underline">{{ $d->codigo }}</a> · {{ $d->estado->label() }}.</span>
        @endforeach
    </div>
@endif

@if ($incidencia->equipos->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h3 class="card-title flex items-center gap-2">
                <x-icon name="monitor" class="size-4" />
                {{ $incidencia->equipos->count() === 1 ? 'Equipo afectado' : 'Equipos afectados ('.$incidencia->equipos->count().')' }}
            </h3>
            @if ($incidencia->equipos->count() > 1)
                <a href="{{ route('incidencias.informes-equipos', $incidencia) }}" target="_blank" class="btn btn-secondary btn-sm">
                    <x-icon name="pdf" class="size-4" /> Todos los informes
                </a>
            @endif
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($incidencia->equipos as $e)
                <div class="card-body space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-800">{{ $e->codigo }} · {{ trim($e->categoria.' '.$e->marca.' '.$e->modelo) }}
                                <x-badge :color="$e->estadoColor()" class="ml-1">{{ $e->estadoLabel() }}</x-badge></p>
                            <p class="text-xs text-slate-500">{{ $e->serie ? 'S/N '.$e->serie : 'Sin serie legible' }}{{ collect([$e->area, $e->ubicacion])->filter()->isNotEmpty() ? ' · '.collect([$e->area, $e->ubicacion])->filter()->implode(' · ') : '' }}{{ $e->responsable ? ' · '.$e->responsable : '' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('incidencias.informe-equipo', [$incidencia, $e]) }}" target="_blank" class="btn btn-secondary btn-sm">
                                <x-icon name="pdf" class="size-4" /> Informe técnico
                            </a>
                            @can('equipos.ver')
                                <a href="{{ route('equipos.show', $e) }}" class="btn btn-secondary btn-sm"><x-icon name="history" class="size-4" /> Historial</a>
                            @endcan
                        </div>
                    </div>
                    @if ($e->eventos->isNotEmpty())
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-900">Antecedentes del equipo</p>
                            <ul class="mt-2 space-y-1.5 text-sm text-amber-900">
                                @foreach ($e->eventos as $ev)
                                    <li><span class="font-medium">{{ $ev->fecha->format('d/m/Y') }} · {{ $ev->tipoLabel() }}:</span> {{ $ev->titulo }}{{ $ev->componente ? ' ('.$ev->componente.')' : '' }}
                                        @if ($ev->detalle)<span class="block text-xs text-amber-800/80">{{ \Illuminate\Support\Str::limit($ev->detalle, 160) }}</span>@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Sin antecedentes registrados para este equipo.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
