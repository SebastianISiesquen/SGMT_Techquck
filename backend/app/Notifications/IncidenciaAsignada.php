<?php

namespace App\Notifications;

use App\Models\Incidencia;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidenciaAsignada extends Notification
{
    use Canales;

    public function __construct(public Incidencia $incidencia, public bool $reabierta = false) {}

    public function toArray(object $notifiable): array
    {
        $i = $this->incidencia;

        return [
            'tipo' => 'incidencia',
            'titulo' => $this->reabierta ? "Incidencia reabierta {$i->codigo}" : "Nueva incidencia asignada {$i->codigo}",
            'mensaje' => "{$i->titulo} · {$i->proyecto->nombre} · Prioridad {$i->prioridad->label()}",
            'url' => route('mis-incidencias.show', $i),
            'incidencia_id' => $i->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($d['titulo'])
            ->greeting("Hola {$notifiable->name}")
            ->line($d['mensaje'])
            ->line('Entidad: '.$this->incidencia->proyecto->entidad->nombre)
            ->action('Ver incidencia', $d['url']);
    }
}
