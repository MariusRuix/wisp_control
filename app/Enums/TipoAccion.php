<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoAccion: string
{
    case Cortar = 'cortar';
    case Reconectar = 'reconectar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cortar => 'Corte',
            self::Reconectar => 'Reconexión',
        };
    }

    /** Estado administrativo que debe quedar en la ONU tras la acción. */
    public function estadoResultante(): string
    {
        return match ($this) {
            self::Cortar => 'Disabled',
            self::Reconectar => 'Enabled',
        };
    }
}
