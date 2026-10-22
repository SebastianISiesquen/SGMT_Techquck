<?php

namespace App\Notifications;

use App\Models\Incidencia;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** El supervisor fijó o cambió la fecha de la próxima visita de una incidencia. */
class VisitaReprogramada extends Notification
{
    use Canales;

    public function __construct(public Incidencia $incidencia) {}

    public function toArray(object $notifiable): array
    {
        $i = $this->incidencia;

        return [
            'tipo' => 'incidencia',
            'titulo' => "Próxima visita: {$i->proxima_visita->format('d/m/Y')} · {$i->codigo}",
            'mensaje' => "{$i->titulo} · {$i->proyecto->lugarDeServicio()->nombre}".($i->motivo_reprogramacion ? " · {$i->motivo_reprogramacion}" : ''),
            'url' => route('mis-incidencias.show', $i),
            'incidencia_id' => $i->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)->subject($d['titulo'])->line($d['mensaje'])->action('Ver incidencia', $d['url']);
    }
}
