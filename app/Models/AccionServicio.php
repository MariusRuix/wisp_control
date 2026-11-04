<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccionServicio extends Model
{
    protected $table = 'acciones_servicio';

    protected $fillable = [
        'onu_id', 'user_id', 'tipo', 'estado', 'motivo', 'error', 'intentos', 'ejecutada_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAccion::class,
            'estado' => EstadoAccion::class,
            'ejecutada_en' => 'datetime',
        ];
    }

    public function onu(): BelongsTo
    {
        return $this->belongsTo(Onu::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
