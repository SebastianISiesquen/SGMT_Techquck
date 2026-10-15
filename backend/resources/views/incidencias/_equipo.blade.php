{{-- Equipo afectado y sus antecedentes: lo que otros técnicos encontraron antes. --}}
@if ($incidencia->equipo)
    @php($e = $incidencia->equipo)
    <div class="card">
        <div class="card-header">
            <h3 class="card-title flex items-center gap-2"><x-icon name="monitor" class="size-4" /> Equipo afectado</h3>
            <x-badge :color="$e->estadoColor()">{{ $e->estadoLabel() }}</x-badge>
        </div>
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-slate-800">{{ $e->codigo }} · {{ trim($e->categoria.' '.$e->marca.' '.$e->modelo) }}</p>
                    <p class="text-xs text-slate-500">{{ $e->serie ? 'S/N '.$e->serie : 'Sin serie legible' }}{{ collect([$e->area, $e->ubicacion])->filter()->isNotEmpty() ? ' · '.collect([$e->area, $e->ubicacion])->filter()->implode(' · ') : '' }}{{ $e->responsable ? ' · '.$e->responsable : '' }}</p>
                </div>
                @can('equipos.ver')
                    <a href="{{ route('equipos.show', $e) }}" class="btn btn-secondary btn-sm"><x-icon name="history" class="size-4" /> Historial completo</a>
                @endcan
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
    </div>
@endif
