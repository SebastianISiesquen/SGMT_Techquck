<x-layouts.app title="Equipos">
    <x-page-header title="Equipos" subtitle="Consulta de todos los equipos y su historial: busque por serie, por el código del sticker o por cliente. Los equipos se registran dentro de cada proyecto o servicio." />

    <div class="mb-4 flex flex-wrap gap-2">
        @php($totalResumen = $resumen->sum())
        <a href="{{ request()->fullUrlWithQuery(['estado' => null, 'page' => null]) }}"
           class="rounded-full px-3 py-1 text-xs font-medium {{ request('estado') ? 'bg-white text-slate-600 ring-1 ring-slate-200' : 'bg-slate-900 text-white' }}">Todos · {{ $totalResumen }}</a>
        @foreach (\App\Models\Equipo::ESTADOS as $k => $v)
            @if ($resumen[$k] ?? 0)
                <a href="{{ request()->fullUrlWithQuery(['estado' => $k, 'page' => null]) }}"
                   class="rounded-full px-3 py-1 text-xs font-medium {{ request('estado') === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $v }} · {{ $resumen[$k] }}</a>
            @endif
        @endforeach
    </div>

    <div class="card" x-data="{ marcados: [] }">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-input name="buscar" :value="request('buscar')" placeholder="Serie, código EQ-…, marca, modelo, responsable…" class="lg:col-span-2" autofocus />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" onchange="this.form.proyecto_id && (this.form.proyecto_id.value = ''); this.form.submit()" />
            <x-select name="proyecto_id" :options="$proyectos" :value="request('proyecto_id')" :placeholder="request('entidad_id') ? 'Todos los proyectos' : 'Elija un cliente'" />
            <x-select name="categoria" :options="$categorias" :list="true" :value="request('categoria')" placeholder="Todas las categorías" />
            <div class="flex gap-2">
                <input type="hidden" name="estado" value="{{ request('estado') }}">
                <button class="btn btn-secondary flex-1"><x-icon name="search" class="size-4" /> Buscar</button>
            </div>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            <span x-show="!marcados.length">{{ $equipos->total() }} equipo(s). Marque equipos para imprimir sus stickers con QR.</span>
            <span x-show="marcados.length" x-cloak><strong x-text="marcados.length"></strong> seleccionado(s)</span>
            <div class="flex gap-2">
                <a :href="'{{ route('equipos.etiquetas') }}?' + marcados.map(id => 'ids[]=' + id).join('&')" x-show="marcados.length" x-cloak target="_blank" class="btn btn-secondary btn-sm"><x-icon name="qr" class="size-4" /> Stickers de los seleccionados</a>
                @if ($equipos->total())
                    <a href="{{ route('equipos.etiquetas', request()->query()) }}" target="_blank" class="btn btn-ghost btn-sm" x-show="!marcados.length"><x-icon name="printer" class="size-4" /> Stickers de esta búsqueda</a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>
                    <th class="w-8"><input type="checkbox" class="rounded border-slate-300" @change="marcados = $event.target.checked ? @js($equipos->pluck('id')) : []"></th>
                    <th>Código</th><th>Equipo</th><th>Serie</th><th>Cliente / ubicación</th><th>Responsable</th><th class="text-center">Fallas</th><th>Última actividad</th><th>Estado</th><th></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($equipos as $e)
                    <tr>
                        <td><input type="checkbox" value="{{ $e->id }}" x-model.number="marcados" class="rounded border-slate-300"></td>
                        <td class="whitespace-nowrap font-mono text-xs"><a href="{{ route('equipos.show', $e) }}" class="font-semibold text-brand-600 hover:underline">{{ $e->codigo }}</a></td>
                        <td class="max-w-56"><a href="{{ route('equipos.show', $e) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $e->categoria }}</a>
                            <p class="truncate text-xs text-slate-500">{{ trim($e->marca.' '.$e->modelo) ?: '—' }}{{ $e->descripcion ? ' · '.$e->descripcion : '' }}</p></td>
                        <td class="whitespace-nowrap font-mono text-xs">{{ $e->serie ?? '—' }}</td>
                        <td class="max-w-56"><p class="truncate">{{ $e->entidad->nombre }}</p>
                            <p class="truncate text-xs text-slate-500">{{ collect([$e->area, $e->ubicacion])->filter()->implode(' · ') ?: ($e->proyecto?->nombre ?? '') }}</p></td>
                        <td>{{ $e->responsable ?? '—' }}</td>
                        <td class="text-center tabular-nums">@if ($e->incidencias_count)<x-badge color="amber">{{ $e->incidencias_count }}</x-badge>@else 0 @endif</td>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $e->ultimo_evento ? \Illuminate\Support\Carbon::parse($e->ultimo_evento)->format('d/m/Y') : '—' }}</td>
                        <td><x-badge :color="$e->estadoColor()">{{ $e->estadoLabel() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('equipos.show', $e) }}" class="btn btn-ghost btn-sm" title="Historial"><x-icon name="history" class="size-4" /></a>
                            <a href="{{ route('equipos.hoja-vida', $e) }}" target="_blank" class="btn btn-ghost btn-sm" title="Hoja de vida (PDF)"><x-icon name="pdf" class="size-4" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty icon="monitor" text="No se encontraron equipos.">
                        <p class="mt-2 text-xs text-slate-500">Los equipos se registran en la ficha de cada proyecto o servicio (también desde Excel), o se crean solos cuando el técnico anota la serie en una hoja de servicio.</p>
                    </x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($equipos->hasPages())<div class="border-t border-slate-100 p-4">{{ $equipos->links() }}</div>@endif
    </div>
</x-layouts.app>
