<x-layouts.app :title="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'">
    <x-page-header :title="$usuario->exists ? 'Editar usuario' : 'Nuevo usuario'" :back="route('usuarios.index')" />

    @php
        $rolActual = old('rol', $usuario->exists ? $usuario->rolNombre() : 'Técnico');
        $porRol = $roles->mapWithKeys(fn ($r) => [$r->name => $r->name === 'Administrador' ? ['*'] : $r->permissions->pluck('name')->all()]);
        $extras = old('permisos', $permisosDirectos);
    @endphp

    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}"
          x-data="{ rol: @js($rolActual), porRol: @js($porRol), extras: @js($extras),
                    delRol(p) { const l = this.porRol[this.rol] || []; return l.includes('*') || l.includes(p) } }">
        @csrf
        @if ($usuario->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-1">
                <div class="card-header"><h3 class="card-title">Datos de la cuenta</h3></div>
                <div class="card-body space-y-4">
                    <x-input name="name" label="Nombres y apellidos" :value="$usuario->name" required />
                    <x-input name="email" type="email" label="Correo (usuario)" :value="$usuario->email" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-input name="dni" label="DNI" :value="$usuario->dni" pattern="[0-9]{8}" inputmode="numeric" maxlength="8" data-mensaje="El DNI debe tener 8 dígitos." />
                        <x-input name="telefono" label="Teléfono" :value="$usuario->telefono" pattern="[0-9+\(\)\s\-]{6,20}" inputmode="tel" maxlength="20" data-mensaje="Ingrese un teléfono válido (solo números, espacios, + o guiones)." />
                    </div>
                    <x-input name="cargo" label="Cargo" :value="$usuario->cargo" />
                    <x-input name="password" type="password" :label="$usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña'" :required="! $usuario->exists"
                             help="Mínimo 8 caracteres con letras y números." autocomplete="new-password" />
                    <x-input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />
                </div>
            </div>

            <div class="card lg:col-span-2">
                <div class="card-header"><h3 class="card-title">Rol y módulos habilitados</h3></div>
                <div class="card-body space-y-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($roles as $r)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3" :class="rol === @js($r->name) ? 'border-brand-500 bg-brand-50' : 'border-slate-200'">
                                <input type="radio" name="rol" value="{{ $r->name }}" x-model="rol" class="mt-0.5 text-brand-600">
                                <span>
                                    <span class="block text-sm font-semibold">{{ $r->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ ['Administrador' => 'Acceso total', 'Supervisor' => 'Gestión y seguimiento', 'Técnico' => 'Atención de incidencias'][$r->name] ?? '' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('rol')<p class="error">{{ $message }}</p>@enderror

                    <div>
                        <p class="text-sm text-slate-600">Los módulos marcados en gris vienen del rol. Puede habilitar módulos <strong>adicionales</strong> solo para este usuario.</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($grupos as $grupo => $permisos)
                                <fieldset class="rounded-lg border border-slate-200 p-3">
                                    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $grupo }}</legend>
                                    @foreach ($permisos as $clave => $desc)
                                        <label class="flex items-start gap-2 py-1 text-sm" :class="delRol(@js($clave)) ? 'text-slate-400' : ''">
                                            <input type="checkbox" name="permisos[]" value="{{ $clave }}"
                                                   @change="$event.target.checked ? extras.push(@js($clave)) : (extras = extras.filter(x => x !== @js($clave)))"
                                                   :checked="delRol(@js($clave)) || extras.includes(@js($clave))" :disabled="delRol(@js($clave))"
                                                   class="mt-0.5 rounded border-slate-300 text-brand-600">
                                            <span>{{ $desc }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar usuario</button>
        </div>
    </form>
</x-layouts.app>
