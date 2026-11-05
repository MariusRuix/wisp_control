<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cargo extends Model
{
    protected $fillable = [
        'cliente_id', 'periodo', 'concepto', 'monto', 'vence_el', 'user_id',
        'cancelado_en', 'cancelado_por', 'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'monto' => 'decimal:2',
            'vence_el' => 'date',
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
