<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ajuste;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Configuración de la API de SmartOLT.
 * Prioridad: lo guardado en el panel (tabla `ajustes`) y, si no hay, el .env / config/smartolt.php.
 * El token se guarda cifrado con APP_KEY y nunca se vuelve a mostrar completo.
 */
final class ConfiguracionSmartOlt
{
    public const RUTAS = ['disable', 'enable', 'bulk_disable', 'bulk_enable', 'all_details', 'all_statuses', 'prueba'];

    /** @var Collection<string, Ajuste>|null */
    private ?Collection $cache = null;

    public function subdominio(): string
    {
        return (string) ($this->guardado('subdomain') ?? config('smartolt.subdomain'));
    }

    public function token(): string
    {
        return (string) ($this->guardado('token') ?? config('smartolt.token'));
    }

    public function timeout(): int
    {
        return (int) ($this->guardado('timeout') ?? config('smartolt.timeout', 30));
    }

    public function campoBulk(): string
    {
        return (string) ($this->guardado('bulk_field') ?? config('smartolt.bulk_field', 'onus_external_ids'));
    }

    public function ruta(string $clave): string
    {
        return (string) ($this->guardado("endpoints.{$clave}") ?? config("smartolt.endpoints.{$clave}"));
    }

    /** De dónde sale cada valor: 'panel' o 'env'. */
    public function origen(string $clave): string
    {
        return $this->guardado($clave) !== null ? 'panel' : 'env';
    }

    public function tokenEnmascarado(): ?string
    {
        $t = $this->token();
        if ($t === '') {
            return null;
        }

        return strlen($t) <= 8 ? str_repeat('•', strlen($t)) : str_repeat('•', 8).substr($t, -4);
    }

    /** El token guardado ya no se puede descifrar (cambió APP_KEY). */
    public function tokenIlegible(): bool
    {
        $a = $this->ajustes()->get('smartolt.token');

        return $a !== null && $a->valor !== null && $a->valorPlano() === null;
    }

    public function estaCompleta(): bool
    {
        return $this->subdominio() !== '' && $this->token() !== '';
    }

    public function ultimoCambio(): ?Ajuste
    {
        return $this->ajustes()->sortByDesc('updated_at')->first()?->loadMissing('autor');
    }

    /**
     * @param  array{subdomain: string, token?: ?string, timeout: int, bulk_field: string, endpoints?: array<string, ?string>}  $datos
     *                                                                                                                            token vacío o null = no cambiar
     */
    public function guardar(array $datos, ?User $usuario): void
    {
        $this->poner('subdomain', strtolower(trim($datos['subdomain'])), $usuario);
        $this->poner('timeout', (string) $datos['timeout'], $usuario);
        $this->poner('bulk_field', trim($datos['bulk_field']), $usuario);

        if (! empty($datos['token'])) {
            $this->poner('token', Crypt::encryptString(trim($datos['token'])), $usuario, cifrado: true);
        }

        foreach ($datos['endpoints'] ?? [] as $clave => $ruta) {
            if (! in_array($clave, self::RUTAS, true)) {
                continue;
            }
            $ruta = trim((string) $ruta);
            // Vacío o igual al valor por defecto = usar el de config/smartolt.php
            if ($ruta === '' || $ruta === config("smartolt.endpoints.{$clave}")) {
                Ajuste::where('clave', "smartolt.endpoints.{$clave}")->delete();
            } else {
                $this->poner("endpoints.{$clave}", $ruta, $usuario);
            }
        }

        $this->cache = null;
    }

    /** Borra todo lo guardado en el panel: vuelve a usar el .env. */
    public function restablecer(): void
    {
        Ajuste::where('clave', 'like', 'smartolt.%')->delete();
        $this->cache = null;
    }

    private function poner(string $clave, string $valor, ?User $usuario, bool $cifrado = false): void
    {
        Ajuste::updateOrCreate(
            ['clave' => "smartolt.{$clave}"],
            ['valor' => $valor, 'cifrado' => $cifrado, 'actualizado_por' => $usuario?->id],
        );
    }

    private function guardado(string $clave): ?string
    {
        $v = $this->ajustes()->get("smartolt.{$clave}")?->valorPlano();

        return $v === null || $v === '' ? null : $v;
    }

    /** @return Collection<string, Ajuste> */
    private function ajustes(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            // En una instalación nueva la tabla aún no existe: se usa el .env
            $this->cache = Schema::hasTable('ajustes')
                ? Ajuste::query()->where('clave', 'like', 'smartolt.%')->get()->keyBy('clave')
                : collect();
        } catch (Throwable) {
            $this->cache = collect();
        }

        return $this->cache;
    }
}
