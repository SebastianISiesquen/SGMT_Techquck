<x-layouts.app title="Cotizaciones">
    <x-page-header title="Cotizaciones" subtitle="Propuestas para servicios sin garantía ni contrato: diagnósticos, reparaciones, mantenimientos a pedido.">
        <x-slot:actions>
            @can('cotizaciones.gestionar')
                <a href="{{ route('cotizaciones.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva cotización</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['borrador' => ['Borradores', 'file', 'slate'], 'enviada' => ['Enviadas', 'send', 'brand'], 'aprobada' => ['Aprobadas', 'check-circle', 'green'], 'rechazada' => ['Rechazadas', 'x-circle', 'red']] as $k => [$t, $i, $tono])
            <x-stat :label="$t" :value="$resumen[$k]->cantidad ?? 0" :icon="$i" :tone="$tono" :href="route('cotizaciones.index', ['estado' => $k])"
                    :hint="isset($resumen[$k]) ? 'S/ '.number_format($resumen[$k]->monto, 2) : null" />
        @endforeach
    </div>

    <div class="card">
        <form class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
            <x-input name="buscar" :value="request('buscar')" placeholder="N° o asunto…" class="min-w-56 flex-1" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" class="w-64" />
            <x-select name="estado" :options="\App\Models\Cotizacion::ESTADOS" :value="request('estado')" placeholder="Todos los estados" class="w-44" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>N° / fecha</th><th>Cliente</th><th>Asunto</th><th class="text-right">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($cotizaciones as $c)
                    <tr>
                        <td class="whitespace-nowrap"><a href="{{ route('cotizaciones.show', $c) }}" class="font-medium text-brand-600 hover:underline">{{ $c->numero }}</a>
                            <p class="text-xs text-slate-500">{{ $c->fecha->format('d/m/Y') }}</p></td>
                        <td class="max-w-xs truncate">{{ $c->entidad->nombre }}</td>
                        <td class="max-w-sm"><p class="truncate">{{ $c->asunto }}</p>
                            <p class="truncate text-xs text-slate-500">{{ collect([$c->incidencia?->codigo, $c->equipo?->codigo])->filter()->implode(' · ') }}</p></td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ $c->monto($c->total) }}</td>
                        <td><x-badge :color="$c->estadoColor()">{{ $c->estadoLabel() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('cotizaciones.pdf', $c) }}" target="_blank" class="btn btn-ghost btn-sm" title="PDF"><x-icon name="pdf" class="size-4" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="receipt" text="No hay cotizaciones." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($cotizaciones->hasPages())<div class="border-t border-slate-100 p-4">{{ $cotizaciones->links() }}</div>@endif
    </div>
</x-layouts.app>
