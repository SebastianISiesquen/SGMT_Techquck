{{--
    Atenciones registradas sin hoja de servicio (registros anteriores). Las que vienen de una hoja
    se muestran en «Visitas realizadas», para no repetir la misma información.
--}}
@php($sueltas = $incidencia->atenciones->whereNotIn('id', $incidencia->hojasServicio->pluck('atencion_id')->filter()->all()))
@if ($sueltas->isNotEmpty() || $incidencia->hojasServicio->isEmpty())
<div class="card">
    <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="wrench" class="size-4 text-slate-400" /> {{ $incidencia->hojasServicio->isEmpty() ? 'Visitas realizadas' : 'Otras atenciones' }} ({{ $sueltas->count() }})</h3></div>
    @forelse ($sueltas as $a)
        <div class="border-b border-slate-100 p-5 last:border-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $a->tecnico?->iniciales() }}</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $a->tecnico?->name }}</p>
                        <p class="text-xs text-slate-500">{{ $a->fecha_inicio->format('d/m/Y H:i') }}{{ $a->fecha_fin ? ' – '.$a->fecha_fin->format('H:i') : '' }}
                            · {{ $a->origen === 'movil' ? 'App móvil' : 'Web' }}</p>
                    </div>
                </div>
                <x-badge :color="$a->resultado->color()">{{ $a->resultado->label() }}</x-badge>
            </div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase text-slate-500">Diagnóstico</dt><dd class="mt-0.5 whitespace-pre-line">{{ $a->diagnostico }}</dd></div>
                <div><dt class="text-xs font-medium uppercase text-slate-500">Actividades realizadas</dt><dd class="mt-0.5 whitespace-pre-line">{{ $a->actividades }}</dd></div>
                @if ($a->observaciones)
                    <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-slate-500">Observaciones</dt><dd class="mt-0.5 whitespace-pre-line">{{ $a->observaciones }}</dd></div>
                @endif
                @if ($a->latitud)
                    <div class="sm:col-span-2"><a target="_blank" class="inline-flex items-center gap-1 text-xs text-brand-600 hover:underline" href="https://maps.google.com/?q={{ $a->latitud }},{{ $a->longitud }}"><x-icon name="map-pin" class="size-3.5" /> Ubicación registrada</a></div>
                @endif
            </dl>
            @if ($a->archivos->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($a->archivos as $f)
                        @if ($f->esImagen())
                            <a href="{{ route('archivos.ver', $f) }}" target="_blank" title="{{ $f->descripcion ?: $f->nombre_original }}">
                                <img src="{{ route('archivos.ver', $f) }}" alt="{{ $f->descripcion ?: 'Evidencia' }}" loading="lazy" class="size-20 rounded-lg border border-slate-200 object-cover hover:opacity-80">
                            </a>
                        @else
                            <a href="{{ route('archivos.descargar', $f) }}" class="flex h-20 w-36 flex-col justify-center rounded-lg border border-slate-200 px-3 text-xs hover:bg-slate-50">
                                <span class="font-semibold {{ $f->categoria->value === 'acta_conformidad' ? 'text-emerald-700' : 'text-slate-700' }}">{{ $f->categoria->label() }}</span>
                                <span class="truncate text-slate-500">{{ $f->nombre_original }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <x-empty icon="clipboard" text="Aún no se registran visitas del técnico." />
    @endforelse
</div>
@endif
