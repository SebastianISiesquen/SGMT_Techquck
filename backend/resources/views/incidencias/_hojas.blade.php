{{-- Visitas del técnico: cada hoja de servicio es un día de trabajo, con lo hecho y lo pendiente --}}
@if ($incidencia->hojasServicio->isNotEmpty())
    @php($resColor = ['resuelto' => 'green', 'parcial' => 'amber', 'pendiente_repuesto' => 'blue', 'no_procede' => 'red'])
    <div class="card">
        <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="clipboard" class="size-4 text-slate-400" /> Visitas realizadas ({{ $incidencia->hojasServicio->count() }})</h3></div>
        <div class="card-body">
            @foreach ($incidencia->hojasServicio->sortBy([['fecha', 'asc'], ['id', 'asc']])->values() as $h)
                <div class="relative flex gap-4 pb-5 last:pb-0">
                    @unless ($loop->last)<span class="absolute left-4 top-9 -ml-px h-full w-0.5 bg-slate-200"></span>@endunless
                    <div class="relative flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $loop->iteration }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('hojas.show', $h) }}" class="text-sm font-semibold text-slate-800 hover:text-brand-600">{{ $h->fecha->format('d/m/Y') }} · Hoja {{ $h->numero }}</a>
                            @if ($h->resultado)<x-badge :color="$resColor[$h->resultado] ?? 'slate'" :dot="false">{{ $h->resultadoLabel() }}</x-badge>@endif
                            <x-badge :color="$h->estado->color()">{{ $h->estado->label() }}</x-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $h->tecnico?->name }} · {{ substr($h->hora_llegada, 0, 5) }}–{{ $h->hora_salida ? substr($h->hora_salida, 0, 5) : '…' }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($h->trabajo_realizado, 220) }}</p>
                        @if ($h->requiereVisita())
                            <p class="mt-1 text-sm text-amber-800"><strong>Pendiente:</strong> {{ $h->pendientes }} · <strong>vuelve el {{ $h->proxima_visita?->format('d/m/Y') }}</strong></p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
