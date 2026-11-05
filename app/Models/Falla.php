<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFalla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Falla extends Model
{
    protected $table = 'fallas';

    protected $fillable = [
        'cliente_id', 'reportada_el', 'descripcion', 'estado', 'asignado_a',
        'creado_por', 'estado_red_al_reportar', 'solucion', 'resuelta_el',
    ];

    protected function casts(): array
    {
        return [
            'reportada_el' => 'datetime',
            'resuelta_el' => 'datetime',
            'estado' => EstadoFalla::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->whereIn('estado', EstadoFalla::pendientes());
    }

    public function estaResuelta(): bool
    {
        return $this->estado === EstadoFalla::Resuelta;
    }
}
