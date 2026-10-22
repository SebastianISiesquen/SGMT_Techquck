<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Correo saliente configurable desde la web (Configuración → Correo).
 * Si hay un servidor SMTP guardado, reemplaza lo del .env; si no, se usa MAIL_* del .env.
 */
class Correo
{
    /** Aplica el SMTP guardado en la configuración (se llama al iniciar la aplicación). */
    public static function aplicar(): void
    {
        try {
            $a = Ajustes::all();
        } catch (\Throwable) {
            return; // sin base de datos (instalación inicial): se queda lo del .env
        }

        if (empty($a['correo_host'])) {
            return;
        }

        $cifrado = $a['correo_cifrado'] ?? 'tls';

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $a['correo_host'],
            'mail.mailers.smtp.port' => (int) ($a['correo_puerto'] ?: 587),
            // 465 = SSL directo (smtps); 587 = STARTTLS, que se negocia solo
            'mail.mailers.smtp.scheme' => $cifrado === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $a['correo_usuario'] ?: null,
            'mail.mailers.smtp.password' => self::clave($a['correo_clave'] ?? null),
            'mail.mailers.smtp.timeout' => 20,
            'mail.from.address' => $a['correo_remitente'] ?: $a['correo_usuario'],
            'mail.from.name' => $a['correo_nombre'] ?: ($a['empresa_nombre'] ?? config('app.name')),
        ]);
    }

    /** ¿Los correos salen de verdad? Con «log» solo se escriben en el registro del sistema. («array» es el de las pruebas.) */
    public static function activo(): bool
    {
        return ! in_array(config('mail.default'), ['log', null], true);
    }

    public static function cifrarClave(string $clave): string
    {
        return Crypt::encryptString($clave);
    }

    private static function clave(?string $guardada): ?string
    {
        if (! $guardada) {
            return null;
        }

        try {
            return Crypt::decryptString($guardada);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Envía un correo de prueba; devuelve null si salió bien o el mensaje de error del servidor. */
    public static function probar(string $destino): ?string
    {
        try {
            Mail::raw(
                "Este es un correo de prueba del Sistema de Gestión de Garantías.\n\nSi lo recibió, los avisos de vencimiento y los informes se enviarán correctamente.",
                fn ($m) => $m->to($destino)->subject('Prueba de correo · '.Ajustes::get('empresa_nombre')),
            );

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
