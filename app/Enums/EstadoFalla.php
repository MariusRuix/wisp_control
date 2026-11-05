<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoFalla: string
{
    case Abierta = 'abierta';
    case EnProceso = 'en_proceso';
    case Resuelta = 'resuelta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnProceso => 'En proceso',
            self::Resuelta => 'Resuelta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Abierta => 'bg-red-100 text-red-800',
            self::EnProceso => 'bg-amber-100 text-amber-800',
            self::Resuelta => 'bg-emerald-100 text-emerald-800',
        };
    }

    /** @return list<string> */
    public static function pendientes(): array
    {
        return [self::Abierta->value, self::EnProceso->value];
    }
}
