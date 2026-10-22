<?php

namespace App\Services;

use App\Models\Garantia;
use App\Models\User;
use App\Notifications\AlertaGarantia;
use App\Notifications\AvisoVencimientoCliente;
use App\Support\Ajustes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Avisos de vencimiento de garantías en hitos configurables (p. ej. 60, 30, 15 y 7 días)
 * más el día del vencimiento. Cada hito se envía una sola vez por garantía, aunque
 * el proceso diario no se haya ejecutado algún día.
 */
class AlertaGarantiaService
{
    /** Motivo del último envío fallido al cliente (para mostrarlo en pantalla). */
    public ?string $ultimoError = null;

    /** @return int[] hitos de mayor a menor, siempre incluye 0 (día del vencimiento) */
    public static function hitos(): array
    {
        return collect(explode(',', (string) Ajustes::get('avisos_garantia')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0 && $d <= 365)
            ->push(0)
            ->unique()->sortDesc()->values()->all();
    }

    /** Revisa todas las garantías activas o recién vencidas y envía los avisos pendientes. */
    public function procesar(): int
    {
        $enviados = 0;

        Garantia::with(['proyecto.entidad', 'proyecto.clienteFinal', 'proyecto.responsable', 'alertas'])
            ->where('anulada', false)
            ->whereDate('fecha_vencimiento', '>=', today()->subDays(7))
            ->whereDate('fecha_vencimiento', '<=', today()->addDays(max(self::hitos())))
            ->each(function (Garantia $g) use (&$enviados) {
                $hito = $this->hitoPendiente($g);
                if ($hito !== null) {
                    $this->enviar($g, $hito);
                    $enviados++;
                }
            });

        return $enviados;
    }

    /** El hito más cercano ya alcanzado que todavía no se avisó (null si no corresponde avisar). */
    public function hitoPendiente(Garantia $g): ?int
    {
        $dias = $g->diasRestantes();
        $hito = collect(self::hitos())->filter(fn ($h) => $h >= max(0, $dias))->min();

        if ($hito === null || $g->alertas->contains('dias', $hito)) {
            return null;
        }

        return $hito;
    }

    /** Envía el aviso a los supervisores (sistema y correo) y, si corresponde, al cliente. */
    public function enviar(Garantia $g, ?int $hito = null, bool $soloCliente = false): array
    {
        $dias = $g->diasRestantes();
        $correosCliente = $this->correosCliente($g);
        $avisados = [];

        $this->ultimoError = null;
        if ($correosCliente && ! \App\Support\Correo::activo()) {
            $this->ultimoError = 'el correo del sistema no está configurado';
        } elseif ($correosCliente) {
            try {
                Notification::route('mail', $correosCliente)->notify(new AvisoVencimientoCliente($g, $dias));
                $avisados = $correosCliente;
            } catch (\Throwable $e) {
                $this->ultimoError = $e->getMessage();
                Log::warning("No se pudo enviar el aviso de la garantía {$g->codigo} al cliente: {$e->getMessage()}");
            }
        }

        if (! $soloCliente) {
            $internos = $this->destinatariosInternos($g);
            Notification::send($internos, new AlertaGarantia($g, $dias, (bool) $avisados));
            $avisados = [...$avisados, ...$internos->pluck('email')->all()];
        }

        if ($hito !== null) {
            $g->alertas()->updateOrCreate(['dias' => $hito], [
                'destinatarios' => implode(', ', $avisados),
                'enviada_en' => now(),
            ]);
        }

        return $avisados;
    }

    /** @return string[] */
    public function correosCliente(Garantia $g): array
    {
        if (! Ajustes::int('avisar_cliente_garantia') || ! $g->notificar_cliente) {
            return [];
        }

        return $g->proyecto->entidad->correosAlerta();
    }

    /** Responsable del proyecto, supervisores con acceso a garantías y administradores. */
    private function destinatariosInternos(Garantia $g): Collection
    {
        return User::activos()->permission('garantias.ver')->get()
            ->merge(User::role('Administrador')->activos()->get())
            ->when($g->proyecto->responsable?->activo, fn ($c) => $c->push($g->proyecto->responsable))
            ->unique('id')
            ->values();
    }
}
