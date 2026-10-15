<x-layouts.app :title="'Garantía '.$garantia->codigo">
    <x-page-header :title="$garantia->tipo" :subtitle="$garantia->codigo.' · '.$garantia->proyecto->nombre" :back="route('garantias.index')">
        <x-slot:actions>
            <a href="{{ route('garantias.carta', $garantia) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> Carta de garantía</a>
            @can('garantias.gestionar')
                <a href="{{ route('garantias.edit', $garantia) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
                @if ($garantia->incidencias->isEmpty())
                    <form method="POST" action="{{ route('garantias.destroy', $garantia) }}" data-confirmar="¿Eliminar la garantía?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, eliminar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                        @csrf @method('DELETE')
                        <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="size-4" /></button>
                    </form>
                @endif
            @endcan
            @can('incidencias.gestionar')
                <a href="{{ route('incidencias.create', ['proyecto_id' => $garantia->proyecto_id, 'garantia_id' => $garantia->id]) }}" class="btn btn-primary"><x-icon name="alert" class="size-4" /> Registrar incidencia</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php($colorBarra = ['green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'slate' => 'bg-slate-400'][$garantia->estado->color()])
    <div class="card mb-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-badge :color="$garantia->estado->color()" class="px-3 py-1 text-sm">{{ $garantia->estado->label() }}</x-badge>
                <p class="text-sm text-slate-600">
                    @if ($garantia->diasRestantes() >= 0)
                        Quedan <strong class="text-slate-900">{{ $garantia->diasRestantes() }}</strong> días de cobertura
                    @else
                        Venció hace {{ abs($garantia->diasRestantes()) }} días
                    @endif
                </p>
            </div>
            <p class="text-sm tabular-nums text-slate-500">{{ $garantia->fecha_inicio->format('d/m/Y') }} → {{ $garantia->fecha_vencimiento->format('d/m/Y') }}</p>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $garantia->avance() }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full {{ $colorBarra }}" style="width: {{ $garantia->avance() }}%"></div>
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ $garantia->avance() }}% del periodo transcurrido</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ficha de la garantía</h3></div>
                <dl class="card-body dl-grid">
                    <div><dt>Proyecto / servicio</dt><dd><a href="{{ route('proyectos.show', $garantia->proyecto) }}" class="text-brand-600 hover:underline">{{ $garantia->proyecto->nombre }}</a></dd></div>
                    <div><dt>Cliente</dt><dd>{{ $garantia->proyecto->entidad->nombre }}
                        @if ($garantia->proyecto->clienteFinal)<span class="block text-xs text-slate-500">Cliente final: {{ $garantia->proyecto->clienteFinal->nombre }}</span>@endif</dd></div>
                    <div><dt>Descripción</dt><dd>{{ $garantia->descripcion }}</dd></div>
                    <div><dt>Duración</dt><dd>{{ $garantia->duracionTexto() }}</dd></div>
                    <div><dt>Orden de compra</dt><dd>{{ $garantia->orden_compra ?: ($garantia->proyecto->orden_compra ?? '—') }}</dd></div>
                    <div><dt>Monto</dt><dd>{{ $garantia->monto ? 'S/ '.number_format($garantia->monto, 2) : '—' }}</dd></div>
                    <div><dt>Proveedor / fabricante</dt><dd>{{ $garantia->proveedor ?? '—' }}</dd></div>
                    <div><dt>N° certificado del fabricante</dt><dd>{{ $garantia->numero_documento ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt>Cobertura</dt><dd class="whitespace-pre-line">{{ $garantia->cobertura ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt>Condiciones y exclusiones</dt><dd class="whitespace-pre-line">{{ $garantia->condiciones ?? '—' }}</dd></div>
                    @if ($garantia->observaciones)
                        <div class="sm:col-span-2"><dt>Observaciones</dt><dd class="whitespace-pre-line">{{ $garantia->observaciones }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Incidencias atendidas con esta garantía ({{ $garantia->incidencias->count() }})</h3></div>
                @forelse ($garantia->incidencias as $i)
                    <a href="{{ route('incidencias.show', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                        <x-badge :color="$i->prioridad->color()">{{ $i->prioridad->label() }}</x-badge>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $i->codigo }} · {{ $i->titulo }}</p>
                            <p class="text-xs text-slate-500">{{ $i->fecha_reporte->format('d/m/Y') }} · {{ $i->tecnico?->name ?? 'Sin asignar' }}</p>
                        </div>
                        <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                    </a>
                @empty
                    <x-empty icon="check-circle" text="No se han reportado incidencias para esta garantía." />
                @endforelse
            </div>
        </div>
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="mail" class="size-4" /> Avisos de vencimiento</h3></div>
                <div class="card-body space-y-3 text-sm">
                    <p class="text-slate-600">Se avisa a los <strong>{{ implode(', ', array_filter($hitos)) }}</strong> días y el día del vencimiento, al supervisor
                        @if ($correosCliente) y al cliente:<span class="block text-xs text-slate-500">{{ implode(', ', $correosCliente) }}</span>
                        @else <span class="block text-xs text-amber-700">El cliente no recibe avisos: {{ $garantia->notificar_cliente ? 'no tiene correos registrados.' : 'la garantía está configurada para no avisarle.' }}</span>@endif
                    </p>
                    @forelse ($garantia->alertas as $a)
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <p class="text-xs font-medium text-slate-700">{{ $a->dias ? "Aviso de {$a->dias} días" : 'Aviso de vencimiento' }} · {{ $a->enviada_en->format('d/m/Y H:i') }}</p>
                            <p class="truncate text-xs text-slate-500" title="{{ $a->destinatarios }}">{{ $a->destinatarios }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Aún no se ha enviado ningún aviso.</p>
                    @endforelse
                    @can('garantias.gestionar')
                        @if ($correosCliente && ! $garantia->anulada)
                            <form method="POST" action="{{ route('garantias.avisar', $garantia) }}" data-confirmar="¿Enviar ahora el aviso de vencimiento al cliente?" data-confirmar-tipo="info" data-confirmar-boton="Enviar">
                                @csrf
                                <button class="btn btn-secondary btn-sm w-full"><x-icon name="send" class="size-4" /> Enviar aviso al cliente ahora</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            @if ($garantia->equipos->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Equipos cubiertos ({{ $garantia->equipos->count() }})</h3></div>
                    @foreach ($garantia->equipos->take(15) as $e)
                        <a href="{{ route('equipos.show', $e) }}" class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-2 text-sm last:border-0 hover:bg-slate-50">
                            <span class="min-w-0 truncate">{{ $e->codigo }} · {{ $e->nombre() }}</span>
                            <x-badge :color="$e->estadoColor()" :dot="false">{{ $e->estadoLabel() }}</x-badge>
                        </a>
                    @endforeach
                    @if ($garantia->equipos->count() > 15)
                        <a href="{{ route('equipos.index', ['proyecto_id' => $garantia->proyecto_id]) }}" class="block px-5 py-2 text-xs text-brand-600">Ver todos</a>
                    @endif
                </div>
            @endif

            <x-archivos :archivos="$garantia->archivos" tipo="garantia" :id="$garantia->id" categoria="carta_garantia"
                        :puede-subir="auth()->user()->can('garantias.gestionar')" titulo="Documentos de la garantía" />
        </div>
    </div>
</x-layouts.app>
