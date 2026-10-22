<?php

namespace App\Notifications;

use App\Models\HojaServicio;
use Illuminate\Notifications\Notification;

/** El técnico subió el acta de conformidad firmada por el cliente. */
class ActaConformidadRecibida extends Notification
{
    public function __construct(public HojaServicio $hoja) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $h = $this->hoja;

        return [
            'tipo' => 'hoja',
            'titulo' => "Acta de conformidad recibida · {$h->numero}",
            'mensaje' => "{$h->tipo_servicio->label()} · {$h->proyecto->lugarDeServicio()->nombre} · {$h->tecnico?->name}",
            'url' => route('hojas.show', $h),
            'incidencia_id' => $h->incidencia_id,
        ];
    }
}
