<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Rol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
        'rol',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'activo' => true,
        'rol' => 'operador',
    ];

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    /**
     * Operadores para asignar trabajo: los activos, más uno específico
     * (p. ej. el asignado actual aunque ya esté desactivado).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function paraAsignar(?int $incluir = null)
    {
        return static::query()
            ->where(fn ($q) => $q->where('activo', true)->when($incluir, fn ($w) => $w->orWhere('id', $incluir)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'rol' => Rol::class,
            'ultimo_acceso' => 'datetime',
        ];
    }
}
