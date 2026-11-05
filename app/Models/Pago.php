<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Pago extends Model
{
    protected $fillable = [
        'cliente_id', 'monto', 'fecha', 'metodo', 'referencia', 'notas', 'user_id',
        'cancelado_en', 'cancelado_por', 'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
            'metodo' => MetodoPago::class,
            'cancelado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereNull('cancelado_en');
    }

    public function estaCancelado(): bool
    {
        return $this->cancelado_en !== null;
    }
}
