<x-layouts.app title="Roles y permisos">
    <x-page-header title="Roles y permisos" subtitle="Defina qué módulos puede usar cada rol. El Administrador siempre tiene acceso total." />

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($roles as $rol)
            @php($actuales = $rol->permissions->pluck('name')->all())
            <form method="POST" action="{{ route('roles.update', $rol) }}" class="card flex flex-col">
                @csrf @method('PUT')
                <div class="card-header">
                    <h3 class="card-title">{{ $rol->name }}</h3>
                    <span class="text-xs text-slate-500">{{ $rol->users_count }} usuario(s)</span>
                </div>
                <div class="card-body flex-1 space-y-4">
                    @foreach ($grupos as $grupo => $permisos)
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $grupo }}</p>
                            @foreach ($permisos as $clave => $desc)
                                <label class="flex items-start gap-2 py-0.5 text-sm {{ $rol->name === 'Administrador' ? 'text-slate-400' : '' }}">
                                    <input type="checkbox" name="permisos[]" value="{{ $clave }}" class="mt-0.5 rounded border-slate-300 text-brand-600"
                                           @checked($rol->name === 'Administrador' || in_array($clave, $actuales)) @disabled($rol->name === 'Administrador')>
                                    {{ $desc }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if ($rol->name !== 'Administrador')
                    <div class="border-t border-slate-100 px-5 py-3 text-right"><button class="btn btn-primary btn-sm">Guardar permisos</button></div>
                @endif
            </form>
        @endforeach
    </div>
</x-layouts.app>
