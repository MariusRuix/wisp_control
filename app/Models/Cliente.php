<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero', 'nombre', 'telefono', 'email', 'direccion',
        'mensualidad', 'dia_pago', 'fecha_alta', 'onu_id', 'no_cortar_hasta', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'mensualidad' => 'decimal:2',
            'dia_pago' => 'integer',
            'fecha_alta' => 'date',
            'no_cortar_hasta' => 'date',
        ];
    }

    public function onu(): BelongsTo
    {
        return $this->belongsTo(Onu::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function fallas(): HasMany
    {
        return $this->hasMany(Falla::class);
    }

    /**
     * Agrega total_cargos, cargos_vencidos y total_pagos (solo vigentes).
     * saldo = total_cargos - total_pagos; vencido = max(0, cargos_vencidos - total_pagos).
     */
    public function scopeConSaldos(Builder $q, ?string $hoy = null): Builder
    {
        $hoy ??= now()->toDateString();

        return $q
            ->addSelect([
                'total_cargos' => Cargo::query()->selectRaw('COALESCE(SUM(monto), 0)')
                    ->whereColumn('cargos.cliente_id', 'clientes.id')->whereNull('cancelado_en'),
                'cargos_vencidos' => Cargo::query()->selectRaw('COALESCE(SUM(monto), 0)')
                    ->whereColumn('cargos.cliente_id', 'clientes.id')->whereNull('cancelado_en')
                    ->whereDate('vence_el', '<', $hoy),
                'total_pagos' => Pago::query()->selectRaw('COALESCE(SUM(monto), 0)')
                    ->whereColumn('pagos.cliente_id', 'clientes.id')->whereNull('cancelado_en'),
            ]);
    }

    public function saldo(): float
    {
        return round((float) ($this->total_cargos ?? $this->cargos()->vigentes()->sum('monto'))
            - (float) ($this->total_pagos ?? $this->pagos()->vigentes()->sum('monto')), 2);
    }

    public function vencido(): float
    {
        $vencidos = $this->cargos_vencidos
            ?? $this->cargos()->vigentes()->whereDate('vence_el', '<', now()->toDateString())->sum('monto');
        $pagos = $this->total_pagos ?? $this->pagos()->vigentes()->sum('monto');

        return max(0.0, round((float) $vencidos - (float) $pagos, 2));
    }

    /** Tiene promesa de pago / convenio vigente. */
    public function protegidoDeCorte(?\DateTimeInterface $hoy = null): bool
    {
        $hoy ??= now();

        return $this->no_cortar_hasta !== null && $this->no_cortar_hasta->copy()->endOfDay()->gte($hoy);
    }

    public function estaDeBaja(): bool
    {
        return $this->trashed();
    }

    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        if ($texto === null || trim($texto) === '') {
            return $q;
        }

        $like = '%'.trim($texto).'%';

        return $q->where(fn (Builder $w) => $w
            ->where('nombre', 'like', $like)
            ->orWhere('numero', 'like', $like)
            ->orWhere('telefono', 'like', $like)
            ->orWhere('direccion', 'like', $like)
            ->orWhereHas('onu', fn (Builder $o) => $o->where('sn', 'like', $like)));
    }
}
