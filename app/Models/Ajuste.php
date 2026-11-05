<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

final class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor', 'cifrado', 'actualizado_por'];

    protected $hidden = ['valor'];

    protected function casts(): array
    {
        return ['cifrado' => 'boolean'];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** Valor ya descifrado. null si no se puede descifrar (p. ej. cambió APP_KEY). */
    public function valorPlano(): ?string
    {
        if ($this->valor === null || ! $this->cifrado) {
            return $this->valor;
        }

        try {
            return Crypt::decryptString($this->valor);
        } catch (DecryptException) {
            return null;
        }
    }
}
