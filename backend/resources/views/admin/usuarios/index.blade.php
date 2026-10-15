<x-layouts.app title="Usuarios">
    <x-page-header title="Usuarios" subtitle="Cuentas de acceso, rol y módulos habilitados.">
        <x-slot:actions>
            <a href="{{ route('usuarios.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo usuario</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
            <x-input name="buscar" :value="request('buscar')" placeholder="Nombre o correo…" class="min-w-56 flex-1" />
            <x-select name="rol" :options="$roles" :list="true" :value="request('rol')" placeholder="Todos los roles" class="w-48" />
            <button class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Usuario</th><th>Rol</th><th>Cargo</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($usuarios as $u)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $u->iniciales() }}</span>
                                <div><p class="font-medium text-slate-900">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }}</p></div>
                            </div>
                        </td>
                        <td><x-badge :color="['Administrador' => 'violet', 'Supervisor' => 'blue', 'Técnico' => 'green'][$u->rolNombre()] ?? 'slate'" :dot="false">{{ $u->rolNombre() }}</x-badge></td>
                        <td>{{ $u->cargo ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ $u->ultimo_acceso?->diffForHumans() ?? 'Nunca' }}</td>
                        <td><x-badge :color="$u->activo ? 'green' : 'slate'">{{ $u->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-ghost btn-sm" title="Editar"><x-icon name="edit" class="size-4" /></a>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('usuarios.toggle', $u) }}" class="inline"
                                      data-confirmar="{{ $u->activo ? '¿Desactivar a '.$u->name.'?' : '¿Activar a '.$u->name.'?' }}"
                                      data-confirmar-detalle="{{ $u->activo ? 'Perderá el acceso de inmediato, también en la app móvil. Su historial se conserva.' : 'Podrá volver a ingresar con su contraseña.' }}"
                                      data-confirmar-tipo="{{ $u->activo ? 'peligro' : 'info' }}" data-confirmar-boton="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-ghost btn-sm {{ $u->activo ? 'text-red-600' : 'text-emerald-600' }}" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                        <x-icon :name="$u->activo ? 'ban' : 'check-circle'" class="size-4" />
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($usuarios->hasPages())<div class="border-t border-slate-100 p-4">{{ $usuarios->links() }}</div>@endif
    </div>
</x-layouts.app>
