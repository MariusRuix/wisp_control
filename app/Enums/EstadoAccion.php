<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoAccion: string
{
    case EnCola = 'en_cola';
    case Exito = 'exito';
    case Fallido = 'fallido';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCola => 'En cola',
            self::Exito => 'Aplicado',
            self::Fallido => 'Falló',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnCola => 'bg-amber-100 text-amber-800',
            self::Exito => 'bg-emerald-100 text-emerald-800',
            self::Fallido => 'bg-red-100 text-red-800',
            self::Anulado => 'bg-slate-100 text-slate-600',
        };
    }
}
