<x-layouts.app :title="$entidad->nombre">
    <x-page-header :title="$entidad->nombre" :subtitle="$entidad->naturalezaLabel().' · '.$entidad->sector.($entidad->ruc ? ' · RUC '.$entidad->ruc : '')" :back="route('entidades.index')">
        <x-slot:actions>
            @can('entidades.gestionar')
                <a href="{{ route('entidades.edit', $entidad) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
                @if ($entidad->proyectos->isEmpty() && $entidad->proyectosComoClienteFinal->isEmpty() && ! $entidad->equipos_count)
                    <form method="POST" action="{{ route('entidades.destroy', $entidad) }}" data-confirmar="¿Eliminar el cliente?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, eliminar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                        @csrf @method('DELETE')
                        <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="size-4" /> Eliminar</button>
                    </form>
                @endif
            @endcan
            @can('equipos.ver')
                <a href="{{ route('equipos.index', ['entidad_id' => $entidad->id]) }}" class="btn btn-secondary"><x-icon name="monitor" class="size-4" /> Equipos ({{ $entidad->equipos_count }})</a>
            @endcan
            @can('proyectos.gestionar')
                <a href="{{ route('proyectos.create', ['entidad_id' => $entidad->id]) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo proyecto o servicio</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del cliente</h3>
                    <x-badge :color="$entidad->activo ? 'green' : 'slate'">{{ $entidad->activo ? 'Activo' : 'Inactivo' }}</x-badge></div>
                <dl class="card-body dl-grid sm:grid-cols-1">
                    <div><dt>Tipo</dt><dd><x-badge :color="$entidad->naturaleza === 'privado' ? 'violet' : 'blue'">{{ $entidad->naturalezaLabel() }}</x-badge> {{ $entidad->sector }}</dd></div>
                    <div><dt>Dirección</dt><dd>{{ $entidad->direccion ?? '—' }}</dd></div>
                    <div><dt>Departamento</dt><dd>{{ $entidad->departamento ?? '—' }}</dd></div>
                    <div><dt>Contacto</dt><dd>{{ $entidad->contacto_nombre ?? '—' }}<span class="block text-xs text-slate-500">{{ $entidad->contacto_cargo }}</span></dd></div>
                    <div><dt>Teléfono</dt><dd>{{ $entidad->contacto_telefono ?? '—' }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ $entidad->contacto_email ?? '—' }}</dd></div>
                    <div><dt>Reciben avisos de vencimiento</dt><dd>{{ implode(', ', $entidad->correosAlerta()) ?: 'Nadie (no tiene correos registrados)' }}</dd></div>
                </dl>
            </div>
            @if ($entidad->equipos_count)
                <a href="{{ route('equipos.index', ['entidad_id' => $entidad->id]) }}" class="card flex items-center gap-4 p-4 hover:border-brand-200">
                    <div class="flex size-11 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="monitor" class="size-5" /></div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-800">{{ $entidad->equipos_count }} equipos registrados</p>
                        <p class="text-xs {{ $entidad->equipos_con_falla_count ? 'text-red-600' : 'text-slate-500' }}">{{ $entidad->equipos_con_falla_count }} con falla o en reparación</p>
                    </div>
                </a>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Proyectos y servicios contratados ({{ $entidad->proyectos->count() }})</h3></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Proyecto / servicio</th><th>Tipo</th><th>Entrega</th><th class="text-center">Garantías</th><th>Estado</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse ($entidad->proyectos as $p)
                            <tr>
                                <td><a href="{{ route('proyectos.show', $p) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $p->nombre }}</a>
                                    <p class="text-xs text-slate-500">{{ $p->codigo }}@if ($p->clienteFinal) · Cliente final: {{ $p->clienteFinal->nombre }}@endif</p></td>
                                <td>{{ $p->tipo }}<p class="text-xs text-slate-500">{{ $p->esServicio() ? 'Servicio' : 'Implementación' }}</p></td>
                                <td class="tabular-nums">{{ $p->fecha_entrega->format('d/m/Y') }}</td>
                                <td class="text-center">{{ $p->garantias_count }}</td>
                                <td><x-badge :color="$p->estado === 'operativo' ? 'green' : 'slate'">{{ $p->estadoLabel() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty icon="folder" text="El cliente aún no tiene proyectos registrados." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($entidad->proyectosComoClienteFinal->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Como cliente final (subcontratos)</h3></div>
                    @foreach ($entidad->proyectosComoClienteFinal as $p)
                        <a href="{{ route('proyectos.show', $p) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $p->nombre }}</p>
                                <p class="text-xs text-slate-500">{{ $p->codigo }} · Contratado por {{ $p->entidad->nombre }}</p>
                            </div>
                            <x-badge :color="$p->estado === 'operativo' ? 'green' : 'slate'">{{ $p->estadoLabel() }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
