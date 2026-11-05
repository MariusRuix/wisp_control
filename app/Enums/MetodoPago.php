<?php

declare(strict_types=1);

namespace App\Enums;

enum MetodoPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Deposito = 'deposito';
    case Tarjeta = 'tarjeta';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Deposito => 'Depósito',
            self::Tarjeta => 'Tarjeta',
            self::Otro => 'Otro',
        };
    }
}
