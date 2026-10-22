<?php

namespace App\Providers;

use App\Models\Atencion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Servidor de correo configurado desde la web (Configuración → Correo)
        \App\Support\Correo::aplicar();

        // El Administrador tiene acceso a todos los módulos.
        Gate::before(fn (User $user) => $user->hasRole('Administrador') ? true : null);

        // Nombres cortos en la columna archivable_type de archivos.
        Relation::enforceMorphMap([
            'entidad' => Entidad::class,
            'proyecto' => Proyecto::class,
            'garantia' => Garantia::class,
            'incidencia' => Incidencia::class,
            'atencion' => Atencion::class,
            'hoja_servicio' => HojaServicio::class,
            'equipo' => Equipo::class,
            'user' => User::class,
        ]);

        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_PE.UTF-8', 'es_ES.UTF-8', 'Spanish');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
