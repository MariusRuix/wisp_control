<?php

declare(strict_types=1);

namespace App\Enums;

/** Qué hará el corte automático con un cliente que debe. */
enum DecisionCorte: string
{
    case Cortar = 'cortar';
    case EnGracia = 'en_gracia';
    case Promesa = 'promesa';
    case SinOnu = 'sin_onu';
    case YaCortado = 'ya_cortado';
    case EnCola = 'en_cola';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cortar => 'Se corta',
            self::EnGracia => 'En gracia',
            self::Promesa => 'Promesa de pago',
            self::SinOnu => 'Sin ONU',
            self::YaCortado => 'Ya cortado',
            self::EnCola => 'Acción en cola',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cortar => 'bg-red-100 text-red-800',
            self::EnGracia => 'bg-amber-100 text-amber-800',
            self::Promesa => 'bg-blue-100 text-blue-800',
            self::SinOnu, self::YaCortado, self::EnCola => 'bg-slate-100 text-slate-600',
        };
    }
}
