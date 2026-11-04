<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Onu extends Model
{
    protected $fillable = [
        'external_id', 'sn', 'nombre', 'direccion', 'contacto',
        'olt_id', 'olt_nombre', 'zona', 'estado_admin', 'estado_red',
        'cliente_externo_id', 'sincronizado_en',
    ];

    protected function casts(): array
    {
        return ['sincronizado_en' => 'datetime'];
    }

    public function acciones(): HasMany
    {
        return $this->hasMany(AccionServicio::class);
    }

    public function accionPendiente(): HasOne
    {
        return $this->hasOne(AccionServicio::class)
            ->where('estado', EstadoAccion::EnCola->value)
            ->latestOfMany();
    }

    public function estaCortada(): bool
    {
        return $this->estado_admin === 'Disabled';
    }

    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        if ($texto === null || trim($texto) === '') {
            return $q;
        }

        $like = '%'.trim($texto).'%';

        return $q->where(fn (Builder $w) => $w
            ->where('nombre', 'like', $like)
            ->orWhere('sn', 'like', $like)
            ->orWhere('external_id', 'like', $like)
            ->orWhere('direccion', 'like', $like)
            ->orWhere('cliente_externo_id', 'like', $like));
    }
}
