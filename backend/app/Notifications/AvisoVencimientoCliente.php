<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Support\Ajustes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo al cliente: su garantía está por vencer (o venció). Busca que el cliente
 * programe su último mantenimiento o negocie la renovación del servicio.
 */
class AvisoVencimientoCliente extends Notification
{
    public function __construct(public Garantia $garantia, public int $dias) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $g = $this->garantia->loadMissing('proyecto.entidad', 'proyecto.clienteFinal');
        $p = $g->proyecto;
        $empresa = Ajustes::get('empresa_nombre');
        $vence = $g->fecha_vencimiento->format('d/m/Y');

        $asunto = $this->dias > 0
            ? "Su garantía {$g->codigo} vence en {$this->dias} días ({$vence})"
            : "Su garantía {$g->codigo} venció el {$vence}";

        $mail = (new MailMessage)
            ->subject("$asunto · $empresa")
            ->greeting('Estimado(a) '.($p->entidad->contacto_nombre ?: $p->entidad->nombre).':')
            ->line($this->dias > 0
                ? "Le recordamos que la garantía de **{$p->nombre}** vence el **{$vence}**, dentro de {$this->dias} días."
                : "Le informamos que la garantía de **{$p->nombre}** venció el **{$vence}**.")
            ->line("**Garantía:** {$g->codigo} · {$g->tipo} ({$g->duracionTexto()})");

        if ($p->clienteFinal) {
            $mail->line("**Cliente final:** {$p->clienteFinal->nombre}");
        }
        if ($g->orden_compra ?: $p->orden_compra) {
            $mail->line('**Orden de compra:** '.($g->orden_compra ?: $p->orden_compra));
        }
        if ($g->cobertura) {
            $mail->line("**Cobertura:** {$g->cobertura}");
        }

        $contacto = collect([Ajustes::get('empresa_telefono'), Ajustes::get('empresa_email')])->filter()->implode(' · ');

        return $mail
            ->line($this->dias > 0
                ? 'Si lo desea, podemos coordinar una revisión de los equipos antes del vencimiento y enviarle una propuesta para renovar la cobertura o contratar el mantenimiento.'
                : 'Si desea renovar la cobertura o contratar el mantenimiento de sus equipos, con gusto le enviaremos una propuesta.')
            ->line($contacto ? "Puede comunicarse con nosotros: $contacto." : 'Puede responder a este correo para coordinar.')
            ->salutation("Atentamente,\n$empresa");
    }
}
