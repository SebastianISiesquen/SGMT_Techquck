<?php

namespace App\Enums;

enum CategoriaArchivo: string
{
    case Foto = 'foto';
    case FotoInstalacion = 'foto_instalacion';
    case ActaConformidad = 'acta_conformidad';
    case InformeTecnico = 'informe_tecnico';
    case Contrato = 'contrato';
    case CartaGarantia = 'carta_garantia';
    case Documento = 'documento';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Foto => 'Fotografía',
            self::FotoInstalacion => 'Foto del equipo instalado (FEI)',
            self::ActaConformidad => 'Acta de conformidad',
            self::InformeTecnico => 'Informe técnico',
            self::Contrato => 'Contrato',
            self::CartaGarantia => 'Carta / certificado de garantía',
            self::Documento => 'Documento',
            self::Otro => 'Otro',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
