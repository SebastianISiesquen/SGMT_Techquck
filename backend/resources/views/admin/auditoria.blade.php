<x-layouts.app title="Auditoría">
    <x-page-header title="Auditoría del sistema" subtitle="Registro de las principales acciones realizadas por los usuarios." />

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 lg:grid-cols-6">
            <x-select name="user_id" :options="$usuarios" :value="request('user_id')" placeholder="Todos los usuarios" />
            <x-select name="modulo" :options="$modulos" :list="true" :value="request('modulo')" placeholder="Todos los módulos" />
            <x-select name="accion" :options="$acciones" :list="true" :value="request('accion')" placeholder="Todas las acciones" />
            <x-input name="desde" type="date" :value="request('desde')" />
            <x-input name="hasta" type="date" :value="request('hasta')" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Descripción</th><th>IP</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($registros as $r)
                    <tr x-data="{ ver: false }">
                        <td class="whitespace-nowrap tabular-nums text-xs">{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->usuario?->name ?? '—' }}</td>
                        <td><x-badge :dot="false">{{ $r->modulo }}</x-badge></td>
                        <td class="text-xs font-medium">{{ $r->accion }}</td>
                        <td class="max-w-md">{{ $r->descripcion }}
                            @if ($r->datos)
                                <button type="button" class="ml-1 text-xs text-brand-600 hover:underline" @click="ver = !ver">detalle</button>
                                <pre x-show="ver" x-cloak class="mt-2 overflow-x-auto rounded bg-slate-50 p-2 text-[11px]">{{ json_encode($r->datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </td>
                        <td class="text-xs text-slate-500">{{ $r->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="history" text="Sin registros." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($registros->hasPages())<div class="border-t border-slate-100 p-4">{{ $registros->links() }}</div>@endif
    </div>
</x-layouts.app>
