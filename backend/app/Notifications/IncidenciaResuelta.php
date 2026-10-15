<?php

namespace App\Notifications;

use App\Models\Incidencia;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidenciaResuelta extends Notification
{
    use Canales;

    public function __construct(public Incidencia $incidencia) {}

    public function toArray(object $notifiable): array
    {
        $i = $this->incidencia;

        return [
            'tipo' => 'resuelta',
            'titulo' => "Incidencia {$i->codigo} resuelta, pendiente de cierre",
            'mensaje' => "{$i->titulo} · atendida por ".($i->tecnico?->name ?? 'técnico'),
            'url' => route('incidencias.show', $i),
            'incidencia_id' => $i->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)->subject($d['titulo'])->line($d['mensaje'])->action('Revisar y cerrar', $d['url']);
    }
}
