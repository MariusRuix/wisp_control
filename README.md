# Control de Servicio (cortes/reconexiones vía SmartOLT)

Panel web sencillo en Laravel para que los operadores busquen clientes (ONUs) y
**corten** o **reconecten** su internet a través de la API de SmartOLT, con
bitácora de quién hizo qué.

## Qué hace

- **Login** de operadores (sin registro público; se crean por consola).
- **Clientes**: lista sincronizada desde SmartOLT, búsqueda por nombre / SN /
  dirección / ID, filtro Activos/Cortados y por zona, estado de red (Online, LOS…).
- **Cortar / Reconectar** uno o varios clientes, con motivo opcional.
- **Cola** (`smartolt`): las llamadas a SmartOLT nunca bloquean la pantalla,
  se reintentan ante caídas/timeouts y respetan el `Retry-After` del 429.
- **Bitácora**: cada acción con operador, motivo, intentos y el error de SmartOLT.
- **Sincronización programada**: cambios cada 30 min, estado de red cada 6 min,
  completa diario a las 03:15.

## Instalación

```bash
composer create-project laravel/laravel wisp-cortes
cd wisp-cortes
# copia encima el contenido de este paquete (app/, config/, database/, resources/, routes/, tests/)
cat .env.smartolt.example >> .env    # y edita subdominio + token
php artisan migrate
php artisan operador:crear "Mario Ruiz" mario@importare.mx
php artisan smartolt:sincronizar     # primera carga de clientes
php artisan queue:work --queue=smartolt,default   # en otra terminal (en el VPS: Supervisor)
php artisan serve
```

Producción: `deploy/supervisor-wisp-cortes.conf` (worker + línea de cron del scheduler).

Pruebas (no tocan SmartOLT real, usan `Http::fake`): `php artisan test --filter=CorteServicio`

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `config/smartolt.php` | Subdominio, token, rutas de la API (ajustables) |
| `app/Services/SmartOltService.php` | Única clase que habla HTTP con SmartOLT (`X-Token`, multipart) |
| `app/Services/ProgramadorCortes.php` | Crea registros en bitácora y encola en lotes de 50 |
| `app/Jobs/CambiarEstadoServicio.php` | Ejecuta enable/disable (individual o bulk), reintentos |
| `app/Console/Commands/SincronizarOnus.php` | `smartolt:sincronizar` (paginado, `--cambios`, `--estados`) |
| `app/Console/Commands/CrearOperador.php` | `operador:crear` |
| `app/Http/Controllers/*` | Controladores delgados: listado, acciones, bitácora, login |

## Endpoints de SmartOLT usados

Base: `https://{SMARTOLT_SUBDOMAIN}.smartolt.com`, header `X-Token`.

| Uso | Método y ruta |
|---|---|
| Cortar 1 ONU | `POST /api/onu/disable/{unique_external_id}` |
| Reconectar 1 ONU | `POST /api/onu/enable/{unique_external_id}` |
| Cortar varias (≤50) | `POST /api/onu/bulk_disable` — form-data `onus_external_ids=id1,id2,…` |
| Reconectar varias (≤50) | `POST /api/onu/bulk_enable` — igual |
| Lista de clientes | `GET /api/onu/get_all_onus_details?page=&page_size=100` |
| Estado de red | `GET /api/onu/get_onus_statuses` |

> ⚠️ Verifica antes de producción: las rutas de enable/disable y el nombre del
> campo bulk no aparecían completos en la parte pública del OpenAPI. Si en tu
> cuenta difieren, solo cambia `config/smartolt.php` o `SMARTOLT_BULK_FIELD`.
> Prueba primero cortando y reconectando **una ONU de pruebas**.

## Liga con tu base de datos externa de clientes

La tabla `onus` tiene la columna `cliente_externo_id`. Opciones para llenarla:

1. Poner el ID/contrato del cliente en un campo de SmartOLT (p. ej. el
   *external ID* o el *name*) y mapearlo en `SincronizarOnus`.
2. Agregar una conexión `externa` en `config/database.php` y un comando que
   empate por SN o external_id y actualice `cliente_externo_id`.

Así después puedes automatizar: "clientes con adeudo en la BD externa → cortar".

## Siguientes pasos sugeridos

- Corte automático por falta de pago (comando en el scheduler que llame a `ProgramadorCortes`).
- Roles (solo lectura vs. puede cortar).
- Webhook/SSE de SmartOLT para estado en tiempo real.
- Corte en MikroTik además de la ONU.
