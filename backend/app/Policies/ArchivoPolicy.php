<?php

namespace App\Policies;

use App\Models\Archivo;
use App\Models\Atencion;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;

/** Los archivos heredan los permisos del registro al que pertenecen (RNF08). */
class ArchivoPolicy
{
    public function view(User $user, Archivo $archivo): bool
    {
        $padre = $archivo->archivable;

        return match (true) {
            $padre instanceof Proyecto => $user->can('proyectos.ver'),
            $padre instanceof Garantia => $user->can('garantias.ver'),
            $padre instanceof Incidencia, $padre instanceof Atencion => $user->can('view', $archivo->incidencia()),
            $padre instanceof HojaServicio => $user->can('view', $padre),
            $padre instanceof Equipo => $user->can('equipos.ver'),
            default => false,
        };
    }

    public function delete(User $user, Archivo $archivo): bool
    {
        $padre = $archivo->archivable;

        return match (true) {
            $padre instanceof Proyecto => $user->can('proyectos.gestionar'),
            $padre instanceof Garantia => $user->can('garantias.gestionar'),
            $padre instanceof Incidencia, $padre instanceof Atencion => $user->can('incidencias.gestionar')
                || ($archivo->subido_por === $user->id && $archivo->incidencia()?->estaAbierta()),
            $padre instanceof HojaServicio => $padre->esEditable() && ($archivo->subido_por === $user->id || $user->can('hojas.ver')),
            $padre instanceof Equipo => $user->can('equipos.gestionar'),
            default => false,
        };
    }
}
