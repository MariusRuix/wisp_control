<?php

declare(strict_types=1);

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Operador = 'operador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operador => 'Cajero/Técnico',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Admin => 'Todo: usuarios, cancelaciones de pagos y cargos, cortes masivos.',
            self::Operador => 'Clientes, cobros, fallas y cortes de uno en uno. Sin cancelaciones, sin usuarios y sin cortes masivos.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'bg-indigo-100 text-indigo-800',
            self::Operador => 'bg-slate-100 text-slate-700',
        };
    }
}
