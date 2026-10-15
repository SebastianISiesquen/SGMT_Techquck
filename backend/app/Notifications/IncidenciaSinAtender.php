<?php

namespace App\Notifications;

use App\Models\Incidencia;
use Illuminate\Notifications\Notification;

class IncidenciaSinAtender extends Notification
{
    use Canales;

    public function __construct(public Incidencia $incidencia, public int $dias) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $i = $this->incidencia;

        return [
            'tipo' => 'alerta',
            'titulo' => "Incidencia {$i->codigo} sin atender hace {$this->dias} día(s)",
            'mensaje' => "{$i->titulo} · {$i->estado->label()}".($i->tecnico ? " · {$i->tecnico->name}" : ' · sin técnico asignado'),
            'url' => route('incidencias.show', $i),
            'incidencia_id' => $i->id,
        ];
    }
}
