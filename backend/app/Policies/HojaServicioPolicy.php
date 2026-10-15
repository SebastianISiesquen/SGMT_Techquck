<?php

namespace App\Policies;

use App\Models\HojaServicio;
use App\Models\User;

class HojaServicioPolicy
{
    public function view(User $user, HojaServicio $hoja): bool
    {
        return $user->can('hojas.ver')
            || $hoja->tecnico_id === $user->id
            || ($hoja->incidencia && $user->can('view', $hoja->incidencia));
    }

    /** Editar o firmar: el técnico autor, o un supervisor que también registra hojas. */
    public function update(User $user, HojaServicio $hoja): bool
    {
        return $hoja->esEditable()
            && $user->can('hojas.registrar')
            && ($hoja->tecnico_id === $user->id || $user->can('hojas.ver'));
    }

    public function anular(User $user, HojaServicio $hoja): bool
    {
        return $user->can('hojas.anular') && $hoja->estado->value !== 'anulada';
    }
}
