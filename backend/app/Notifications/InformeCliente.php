<?php

namespace App\Notifications;

use App\Models\Proyecto;
use App\Support\Ajustes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Informe del periodo enviado al cliente, con el PDF adjunto. */
class InformeCliente extends Notification
{
    public function __construct(public Proyecto $proyecto, public Carbon $desde, public Carbon $hasta, private string $pdf) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->proyecto;
        $periodo = $this->desde->format('d/m/Y').' al '.$this->hasta->format('d/m/Y');
        $empresa = Ajustes::get('empresa_nombre');

        return (new MailMessage)
            ->subject("Informe de servicio {$p->codigo} · {$periodo}")
            ->greeting('Estimado cliente:')
            ->line("Adjuntamos el informe de **{$p->nombre}** correspondiente al periodo del {$periodo}.")
            ->line('Incluye los mantenimientos realizados, las incidencias atendidas, el estado de sus equipos y la vigencia de sus garantías.')
            ->salutation("Atentamente,\n{$empresa}")
            ->attachData($this->pdf, "Informe-{$p->codigo}-{$this->desde->format('Y-m')}.pdf", ['mime' => 'application/pdf']);
    }
}
