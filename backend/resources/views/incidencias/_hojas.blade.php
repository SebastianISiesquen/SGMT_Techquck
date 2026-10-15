{{-- Hojas de servicio en campo vinculadas a la incidencia --}}
@if ($incidencia->hojasServicio->isNotEmpty())
    <div class="card">
        <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="clipboard" class="size-4 text-slate-400" /> Hojas de servicio ({{ $incidencia->hojasServicio->count() }})</h3></div>
        @foreach ($incidencia->hojasServicio as $h)
            <a href="{{ route('hojas.show', $h) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800">{{ $h->numero }} · {{ $h->tipo_servicio->label() }}</p>
                    <p class="text-xs text-slate-500">{{ $h->fecha->format('d/m/Y') }}
                        @if ($h->estaFirmada()) · firmada por {{ $h->receptor_nombre }}{{ $h->conforme ? ' (conforme)' : ' (con observaciones)' }} @endif</p>
                </div>
                <x-badge :color="$h->estado->color()">{{ $h->estado->label() }}</x-badge>
            </a>
        @endforeach
    </div>
@endif
