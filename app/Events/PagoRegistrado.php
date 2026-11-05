<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Pago;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara al registrar un pago. El módulo de cortes automáticos lo
 * escuchará para reconectar al cliente si ya no tiene adeudo vencido.
 */
final class PagoRegistrado
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Pago $pago)
    {
    }
}
