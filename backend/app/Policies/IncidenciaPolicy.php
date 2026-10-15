<?php

namespace App\Policies;

use App\Models\Incidencia;
use App\Models\User;

class IncidenciaPolicy
{
    /** Supervisores ven todas; el técnico solo las que tiene asignadas. */
    public function view(User $user, Incidencia $incidencia): bool
    {
        return $user->can('incidencias.ver') || $incidencia->tecnico_id === $user->id;
    }

    public function atender(User $user, Incidencia $incidencia): bool
    {
        return $incidencia->tecnico_id === $user->id && $user->can('atenciones.registrar');
    }

    public function adjuntar(User $user, Incidencia $incidencia): bool
    {
        return $user->can('incidencias.gestionar') || $this->atender($user, $incidencia);
    }
}
