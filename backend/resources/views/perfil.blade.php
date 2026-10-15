<x-layouts.app title="Mi perfil">
    <x-page-header title="Mi perfil" :subtitle="$user->rolNombre().($user->cargo ? ' · '.$user->cargo : '')" />

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('perfil.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h3 class="card-title">Datos personales</h3></div>
            <div class="card-body space-y-4">
                <x-input name="name" label="Nombres y apellidos" :value="$user->name" required />
                <x-input name="email" type="email" label="Correo" :value="$user->email" required />
                <x-input name="telefono" label="Teléfono" :value="$user->telefono" pattern="[0-9+\(\)\s\-]{6,20}" inputmode="tel" maxlength="20" data-mensaje="Ingrese un teléfono válido (solo números, espacios, + o guiones)." />
            </div>
            <div class="border-t border-slate-100 px-5 py-3 text-right"><button class="btn btn-primary">Guardar</button></div>
        </form>

        <form method="POST" action="{{ route('perfil.password') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h3 class="card-title">Cambiar contraseña</h3></div>
            <div class="card-body space-y-4">
                <x-input name="current_password" type="password" label="Contraseña actual" required autocomplete="current-password" />
                <x-input name="password" type="password" label="Nueva contraseña" required help="Mínimo 8 caracteres con letras y números." autocomplete="new-password" />
                <x-input name="password_confirmation" type="password" label="Confirmar nueva contraseña" required autocomplete="new-password" />
            </div>
            <div class="border-t border-slate-100 px-5 py-3 text-right"><button class="btn btn-primary">Actualizar contraseña</button></div>
        </form>
    </div>
</x-layouts.app>
