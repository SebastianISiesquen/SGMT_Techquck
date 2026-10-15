{{-- Trazabilidad del ciclo de atención (RF11) --}}
<div class="card">
    <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="history" class="size-4 text-slate-400" /> Historial</h3></div>
    <ol class="card-body space-y-0">
        @foreach ($incidencia->historial as $h)
            <li class="relative flex gap-3 pb-5 last:pb-0">
                @unless ($loop->last)<span class="absolute top-6 left-[11px] h-full w-px bg-slate-200"></span>@endunless
                <span class="relative mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full {{ $h->estado_nuevo ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' }}">
                    <span class="size-2 rounded-full bg-current"></span>
                </span>
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-slate-800">{{ $h->accionLabel() }}
                        @if ($h->estado_nuevo && $h->estado_nuevo !== $h->estado_anterior)
                            <span class="font-normal text-slate-500">→ {{ $h->estado_nuevo->label() }}</span>
                        @endif
                    </p>
                    @if ($h->detalle)<p class="text-slate-600">{{ $h->detalle }}</p>@endif
                    <p class="text-xs text-slate-400">{{ $h->created_at->format('d/m/Y H:i') }} · {{ $h->usuario?->name ?? 'Sistema' }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</div>
