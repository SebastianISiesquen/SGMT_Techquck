<?php

namespace App\Support;

/**
 * Reglas de validación de formato usadas en todo el sistema (web y API),
 * para que un mismo dato se valide igual en cualquier formulario.
 */
class Reglas
{
    /** RUC peruano: 11 dígitos que empiezan en 10, 15, 16, 17 o 20. */
    public static function ruc(): array
    {
        return ['nullable', 'regex:/^(10|15|16|17|20)\d{9}$/'];
    }

    /** DNI (8 dígitos) o carné de extranjería / pasaporte (9 a 12 caracteres alfanuméricos). */
    public static function documento(): array
    {
        return ['nullable', 'regex:/^(\d{8}|[A-Za-z0-9]{9,12})$/'];
    }

    public static function dni(): array
    {
        return ['nullable', 'digits:8'];
    }

    /** Teléfono fijo o celular: dígitos, espacios, +, guiones y paréntesis (6 a 20 caracteres). */
    public static function telefono(): array
    {
        return ['nullable', 'regex:/^[0-9+()\s-]{6,20}$/'];
    }

    /** Montos en soles: no negativos, hasta 2 decimales. */
    public static function monto(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];
    }

    /** Códigos de documentos (OC, contrato, factura): letras, números y separadores comunes. */
    public static function codigoDocumento(int $max = 60): array
    {
        return ['nullable', 'string', "max:$max", 'regex:/^[\pL0-9 .\/°#_-]+$/u'];
    }
}
