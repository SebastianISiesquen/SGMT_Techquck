<?php

namespace App\Notifications;

use App\Models\HojaServicio;
use Illuminate\Notifications\Notification;

class HojaServicioFirmada extends Notification
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
            'titulo' => "Hoja de servicio {$h->numero} firmada".($h->conforme ? '' : ' con observaciones'),
            'mensaje' => "{$h->tipo_servicio->label()} · {$h->proyecto->entidad->nombre} · {$h->tecnico?->name}",
            'url' => route('hojas.show', $h),
            'incidencia_id' => $h->incidencia_id,
        ];
    }
}
