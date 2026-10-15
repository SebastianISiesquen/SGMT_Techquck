<x-layouts.app :title="$incidencia->codigo">
    <x-page-header :title="$incidencia->titulo" :subtitle="$incidencia->codigo.' · '.$incidencia->proyecto->entidad->nombre" :back="route('mis-incidencias.index')">
        <x-slot:actions>
            <a href="{{ route('incidencias.informe', $incidencia) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> Informe PDF</a>
            @can('hojas.registrar')
                @if ($incidencia->estado->value === 'resuelta')
                    <a href="{{ route('hojas.create', ['incidencia_id' => $incidencia->id]) }}" class="btn btn-secondary"><x-icon name="clipboard" class="size-4" /> Hoja de servicio</a>
                @endif
            @endcan
            @if ($puedeAtender && $incidencia->estado->value === 'asignada')
                <form method="POST" action="{{ route('mis-incidencias.iniciar', $incidencia) }}">
                    @csrf <button class="btn btn-primary"><x-icon name="play" class="size-4" /> Iniciar atención</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="-mt-4 mb-6 flex flex-wrap gap-2">
        <x-badge :color="$incidencia->estado->color()">{{ $incidencia->estado->label() }}</x-badge>
        <x-badge :color="$incidencia->prioridad->color()">Prioridad {{ $incidencia->prioridad->label() }}</x-badge>
        @if ($incidencia->estaVencidaSla())<x-badge color="red">Fuera de plazo</x-badge>@endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Problema reportado</h3></div>
                <div class="card-body space-y-5">
                    <p class="text-sm whitespace-pre-line">{{ $incidencia->descripcion }}</p>
                    <dl class="dl-grid">
                        @php($lugar = $incidencia->proyecto->lugarDeServicio())
                        <div><dt>Cliente (lugar del servicio)</dt><dd>{{ $lugar->nombre }}
                            @if ($incidencia->proyecto->clienteFinal)<span class="block text-xs text-slate-500">Contratado por {{ $incidencia->proyecto->entidad->nombre }}</span>@endif</dd></div>
                        <div><dt>Proyecto / servicio</dt><dd>{{ $incidencia->proyecto->nombre }}</dd></div>
                        <div><dt>Ubicación / equipo</dt><dd>{{ $incidencia->ubicacion_detalle ?? $incidencia->equipo?->ubicacion ?? $incidencia->proyecto->ubicacion ?? '—' }}</dd></div>
                        <div><dt>Contacto en el cliente</dt><dd>{{ $incidencia->reportado_por ?? $lugar->contacto_nombre ?? '—' }}
                            @if ($lugar->contacto_telefono)
                                <a href="tel:{{ $lugar->contacto_telefono }}" class="ml-1 text-brand-600">{{ $lugar->contacto_telefono }}</a>
                            @endif</dd></div>
                        <div><dt>Cobertura</dt><dd>{{ $incidencia->coberturaLabel() }}</dd></div>
                        <div><dt>Fecha límite</dt><dd>{{ $incidencia->fecha_limite?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    </dl>
                    @if ($incidencia->garantia)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                            <p class="flex items-center gap-2 font-medium text-slate-800"><x-icon name="shield" class="size-4" /> {{ $incidencia->garantia->tipo }}
                                <x-badge :color="$incidencia->garantia->estado->color()">{{ $incidencia->garantia->estado->label() }}</x-badge></p>
                            @if ($incidencia->garantia->cobertura)<p class="mt-2 text-slate-600"><strong>Cobertura:</strong> {{ $incidencia->garantia->cobertura }}</p>@endif
                            @if ($incidencia->garantia->condiciones)<p class="mt-1 text-slate-600"><strong>Condiciones:</strong> {{ $incidencia->garantia->condiciones }}</p>@endif
                        </div>
                    @endif
                </div>
            </div>

            @include('incidencias._equipo')

            @if ($puedeAtender)
                {{-- Cada visita se registra en una sola hoja de servicio: diagnóstico, trabajo, equipos, materiales, fotos y firma --}}
                <div class="card border-brand-200 bg-brand-50/40 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="flex items-center gap-2 font-semibold text-slate-800"><x-icon name="clipboard" class="size-5 text-brand-600" /> Registrar la visita</p>
                            <p class="mt-1 text-sm text-slate-600">Llene la hoja de servicio con el diagnóstico, el trabajo realizado, los equipos, los materiales usados y las fotos.
                                El cliente firma la conformidad en la misma hoja.</p>
                        </div>
                        <a href="{{ route('hojas.create', ['incidencia_id' => $incidencia->id]) }}" class="btn btn-primary"><x-icon name="clipboard" class="size-4" /> Llenar hoja de servicio</a>
                    </div>
                </div>
            @endif

            @include('incidencias._atenciones')
            @include('incidencias._hojas')

            <x-archivos :archivos="$incidencia->archivos" tipo="incidencia" :id="$incidencia->id" categoria="acta_conformidad"
                        :puede-subir="auth()->user()->can('adjuntar', $incidencia) && $incidencia->estado->value !== 'cerrada'"
                        titulo="Documentos adicionales (actas, informes)" />
        </div>

        <div>@include('incidencias._historial')</div>
    </div>
</x-layouts.app>
