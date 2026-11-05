<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SmartOltException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Único punto de contacto con la API REST de SmartOLT.
 * No contiene lógica de negocio: solo traduce llamadas HTTP.
 */
final class SmartOltService
{
    public function __construct(private readonly ConfiguracionSmartOlt $cfg)
    {
    }

    private function cliente(): PendingRequest
    {
        $subdominio = $this->cfg->subdominio();
        $token = $this->cfg->token();

        if ($subdominio === '' || $token === '') {
            throw new SmartOltException('Falta configurar SmartOLT: subdominio y token (Configuración › SmartOLT o .env).');
        }

        return Http::baseUrl("https://{$subdominio}.smartolt.com")
            ->withHeaders(['X-Token' => $token])
            ->acceptJson()
            ->timeout($this->cfg->timeout());
    }

    private function ruta(string $clave, ?string $id = null): string
    {
        $ruta = $this->cfg->ruta($clave);

        return $id === null ? $ruta : str_replace('{id}', rawurlencode($id), $ruta);
    }

    /** Corta el servicio de una ONU. */
    public function deshabilitar(string $externalId): array
    {
        return $this->post($this->ruta('disable', $externalId));
    }

    /** Reconecta el servicio de una ONU. */
    public function habilitar(string $externalId): array
    {
        return $this->post($this->ruta('enable', $externalId));
    }

    /**
     * @param  list<string>  $externalIds  máximo config('smartolt.bulk_chunk')
     */
    public function deshabilitarVarias(array $externalIds): array
    {
        return $this->post($this->ruta('bulk_disable'), [
            $this->cfg->campoBulk() => implode(',', $externalIds),
        ]);
    }

    /**
     * @param  list<string>  $externalIds  máximo config('smartolt.bulk_chunk')
     */
    public function habilitarVarias(array $externalIds): array
    {
        return $this->post($this->ruta('bulk_enable'), [
            $this->cfg->campoBulk() => implode(',', $externalIds),
        ]);
    }

    /**
     * Página de ONUs (page_size 1-100). Devuelve el JSON completo:
     * status, page, page_size, total_items, total_pages, onus[].
     */
    public function detallesOnus(int $pagina, int $tamano = 100, array $filtros = []): array
    {
        return $this->get($this->ruta('all_details'), [
            'page' => $pagina,
            'page_size' => $tamano,
            ...$filtros,
        ]);
    }

    /** Estados (Online, Power fail, LOS, Offline) de todas las ONUs. */
    public function estadosOnus(array $filtros = []): array
    {
        return $this->get($this->ruta('all_statuses'), $filtros);
    }

    /**
     * Llamada ligera para comprobar subdominio y token (lista de OLTs).
     *
     * @return array{olts: int, ms: int}
     */
    public function probarConexion(): array
    {
        $inicio = microtime(true);
        $json = $this->get($this->ruta('prueba'));

        return [
            'olts' => is_array($json['response'] ?? null) ? count($json['response']) : 0,
            'ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];
    }

    private function post(string $ruta, array $campos = []): array
    {
        return $this->enviar(fn (PendingRequest $c) => $campos === []
            ? $c->post($ruta)
            // SmartOLT espera multipart/form-data, no JSON.
            : $c->asMultipart()->post($ruta, $this->aMultipart($campos)));
    }

    private function get(string $ruta, array $query = []): array
    {
        return $this->enviar(fn (PendingRequest $c) => $c->get($ruta, $query));
    }

    /**
     * @param  callable(PendingRequest): Response  $llamada
     */
    private function enviar(callable $llamada): array
    {
        try {
            $respuesta = $llamada($this->cliente());
        } catch (ConnectionException $e) {
            throw new SmartOltException('Sin conexión con SmartOLT: '.$e->getMessage());
        }

        $json = $respuesta->json();

        if ($respuesta->status() === 429) {
            throw new SmartOltException(
                'SmartOLT: límite de peticiones alcanzado',
                429,
                (int) ($respuesta->header('Retry-After') ?: 60),
            );
        }

        if ($respuesta->failed() || ! is_array($json) || ($json['status'] ?? false) !== true) {
            $error = is_array($json) ? ($json['error'] ?? $json['response'] ?? null) : null;

            throw new SmartOltException(
                'SmartOLT: '.(is_string($error) ? $error : 'respuesta inesperada (HTTP '.$respuesta->status().')'),
                $respuesta->status(),
            );
        }

        return $json;
    }

    /**
     * @return list<array{name: string, contents: string}>
     */
    private function aMultipart(array $campos): array
    {
        $partes = [];
        foreach ($campos as $nombre => $valor) {
            $partes[] = ['name' => (string) $nombre, 'contents' => (string) $valor];
        }

        return $partes;
    }
}
