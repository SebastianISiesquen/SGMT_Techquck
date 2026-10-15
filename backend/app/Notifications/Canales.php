<?php

namespace App\Notifications;

/** Siempre se guarda en BD (campana del sistema); el correo solo si hay un servidor SMTP configurado. */
trait Canales
{
    public function via(object $notifiable): array
    {
        $mailActivo = ! in_array(config('mail.default'), ['log', 'array', null], true) && $notifiable->email;

        return $mailActivo ? ['database', 'mail'] : ['database'];
    }
}
