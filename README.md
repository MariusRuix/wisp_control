# WISP · Control de Servicio

Panel web para administrar un proveedor de internet (WISP) que usa **SmartOLT**: clientes, cobros, reportes de fallas, cortes y reconexiones de ONUs (manuales y automáticos por adeudo), con bitácora de todo.

- Laravel 11 · PHP 8.2+ · MySQL · Blade + Tailwind
- Toda llamada a SmartOLT pasa por una **cola** (no bloquea la pantalla y se reintenta sola)
- Interfaz y mensajes en español (`lang/es`)

---

## Contenido

1. [Módulos](#módulos)
2. [Roles](#roles)
3. [Instalación local (Laragon)](#instalación-local-laragon)
4. [Datos de prueba](#datos-de-prueba)
5. [Configuración (.env)](#configuración-env)
6. [SmartOLT](#smartolt)
7. [Tareas automáticas](#tareas-automáticas)
8. [Comandos](#comandos)
9. [Instalación en el VPS (producción)](#instalación-en-el-vps-producción)
10. [Pruebas](#pruebas)
11. [Estructura del código](#estructura-del-código)
12. [Pendientes conocidos](#pendientes-conocidos)

---

## Módulos

| Pantalla | Qué hace |
|---|---|
| **Inicio** | 12 tarjetas: cobrado hoy y en el mes, adeudo vencido, clientes para corte, cortados, sin ONU, promesas, fallas pendientes, ONUs con problema de red y estado de SmartOLT. Cada tarjeta lleva a su pantalla. |
| **Clientes** | Alta y edición en modal, ficha con datos, ONU, estado de cuenta, fallas y cortes. Baja (libera la ONU) y reactivación. Promesa de pago ("no cortar hasta"). Importación desde CSV. |
| **Cobros** | Mensualidad automática cada mes, cargos extra, registro de pagos (efectivo, transferencia, depósito, tarjeta), saldo, vencido y días de atraso. Pagos y cargos se **cancelan con motivo**, nunca se borran. |
| **Adeudos** | Clientes con saldo vencido y qué hará el corte automático con cada uno (se corta, en gracia, promesa, ya cortado, sin ONU). |
| **Pagos** | Corte de caja por fechas y método, con totales. |
| **Fallas** | Reportes por cliente: abierta / en proceso / resuelta, quién la atiende, estado de la ONU al reportar y solución. |
| **ONUs** | Copia local de las ONUs de SmartOLT con su cliente del panel, estado de red y corte/reconexión (uno a uno o en grupo). Marca las que ya no existen en SmartOLT. |
| **Bitácora** | Cada corte y reconexión: quién, cuándo, si fue automático y qué respondió SmartOLT. |
| **Usuarios** | Operadores: alta y edición en modal, rol, desactivar/reactivar. |
| **Configuración** | Subdominio y token de SmartOLT desde el panel, probar conexión, sincronizar ahora. |

Todas las listas tienen paginación (25/50/100 por página) y conservan filtros al cambiar de página.

## Roles

| Acción | Administrador | Cajero/Técnico |
|---|:---:|:---:|
| Clientes, cobros (registrar), fallas, cortar/reconectar de uno en uno | ✅ | ✅ |
| Cancelar pagos o cargos | ✅ | ❌ |
| Cortes y reconexiones masivas | ✅ | ❌ |
| Usuarios y roles | ✅ | ❌ |
| Configuración de SmartOLT | ✅ | ❌ |

- Los usuarios nuevos nacen como **Cajero/Técnico**.
- Nadie puede cambiar su propio rol ni desactivarse; siempre queda al menos un administrador activo.
- Los bloqueos están en el servidor (gates en `AppServiceProvider`), no solo en las pantallas.

## Instalación local (Laragon)

Requisitos: Laragon con PHP 8.2+, MySQL y Composer.

```bat
cd C:\laragon\www\wisp-cortes
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate            :: contesta "yes" para crear la BD wisp_cortes
php artisan operador:crear "Tu Nombre" tu@correo.mx     :: crea un Administrador
```

Abrir: `http://wisp-cortes.test` (o `php artisan serve` → `http://127.0.0.1:8000`).

> **Si ves el listado de archivos en lugar del panel:** el sitio de Laragon apunta a la raíz y no a `public/`. Detén Laragon, borra `C:\laragon\etc\apache2\sites-enabled\auto.wisp-cortes.test.conf` y vuelve a iniciar (o cambia `DocumentRoot` a `.../wisp-cortes/public`).

Para que funcionen los cortes y las sincronizaciones, en terminales aparte:

```bat
php artisan queue:work --queue=smartolt,default
php artisan schedule:work
```

> **Después de cada actualización del código corre `php artisan migrate`.** Si falta una migración verás errores como "Unknown column" o "Table doesn't exist".

## Datos de prueba

```bat
php artisan migrate:fresh --seed
```

⚠️ **Borra toda la base local** y la llena con datos ficticios:

- Operadores (contraseña `password1234`): `admin@wisp.test` (Administrador), `laura@wisp.test` y `luis@wisp.test` (Cajero/Técnico), `pedro@wisp.test` (desactivado).
- 45 ONUs `DEMO-…` (no existen en SmartOLT: si se cortan, la orden falla sin afectar a nadie).
- 40 clientes en todos los escenarios (al corriente, atrasado, en gracia, promesa, cortado automático y manual, saldo a favor, sin ONU, baja), 3 meses de cobros, bitácora y fallas.

Protecciones: no corre con `APP_ENV=production`, no agrega nada si hay clientes que no son de prueba y no duplica datos.

## Configuración (.env)

| Variable | Default | Para qué |
|---|---|---|
| `APP_TIMEZONE` | `America/Mexico_City` | Fechas, vencimientos y horario de tareas |
| `APP_LOCALE` | `es` | Mensajes en español |
| `DB_*` | MySQL local `wisp_cortes` | Base de datos |
| `QUEUE_CONNECTION` | `database` | Cola de órdenes a SmartOLT |
| `SESSION_LIFETIME` | `480` | Minutos de sesión |
| `SMARTOLT_SUBDOMAIN` | — | `miempresa` de `miempresa.smartolt.com` |
| `SMARTOLT_TOKEN` | — | API key de SmartOLT (Settings › API KEYS) |
| `SMARTOLT_TIMEOUT` | `30` | Segundos de espera |
| `SMARTOLT_BULK_FIELD` | `onus_external_ids` | Campo de los cortes en grupo |
| `COBROS_DIAS_GRACIA` | `3` | Días después del vencimiento antes de cortar |
| `COBROS_CORTE_AUTOMATICO` | `false` | **Interruptor** del corte automático diario |
| `COBROS_HORA_CORTE` | `09:00` | Hora del corte automático |
| `COBROS_RECONEXION_AUTOMATICA` | `true` | Reconectar al pagar (solo cortes automáticos) |

Los datos de SmartOLT también se pueden capturar en **Configuración › SmartOLT**; lo guardado ahí tiene prioridad sobre el `.env` y el token se guarda cifrado con `APP_KEY` (si cambias `APP_KEY`, hay que volver a capturarlo).

## SmartOLT

Base: `https://{subdominio}.smartolt.com`, header `X-Token`.

| Uso | Ruta |
|---|---|
| Cortar 1 ONU | `POST /api/onu/disable/{id}` |
| Reconectar 1 ONU | `POST /api/onu/enable/{id}` |
| Cortar varias (≤50 por llamada) | `POST /api/onu/bulk_disable` (multipart `onus_external_ids=id1,id2…`) |
| Reconectar varias | `POST /api/onu/bulk_enable` |
| Lista de ONUs | `GET /api/onu/get_all_onus_details?page=&page_size=100` |
| Estados de red | `GET /api/onu/get_onus_statuses` |
| Probar conexión | `GET /api/system/get_olts` |

> ⚠️ **Verificar con la cuenta real** antes de activar cortes automáticos. Si alguna ruta difiere, se cambia en **Configuración › SmartOLT › Avanzado** sin tocar el código.

**Flujo de una orden:** botón o corte automático → registro en Bitácora "En cola" → job `CambiarEstadoServicio` en la cola `smartolt` → SmartOLT → "Aplicado" o "Falló" (reintenta hasta 5 veces y respeta el límite de peticiones 429).

**ONUs eliminadas:** la sincronización completa marca las ONUs que SmartOLT ya no devuelve; no se les mandan órdenes y no se ofrecen a clientes. Si faltan más del 20%, no marca nada (posible falla de la API); para forzar: `php artisan smartolt:sincronizar --forzar`.

## Tareas automáticas

Requieren el cron de `schedule:run` (producción) o `php artisan schedule:work` (local), más el proceso de la cola.

| Cuándo | Tarea |
|---|---|
| Cada 6 min | `smartolt:sincronizar --estados` — estado de red |
| Cada 30 min | `smartolt:sincronizar --cambios` — ONUs nuevas o modificadas |
| 00:10 diario | `cobros:generar-cargos` — mensualidad del mes (no duplica) |
| 03:15 diario | `smartolt:sincronizar` — copia completa y ONUs eliminadas |
| `COBROS_HORA_CORTE` diario | `cortes:automaticos` — solo si `COBROS_CORTE_AUTOMATICO=true` |

**Regla del corte automático:** se corta a quien tiene saldo vencido, más días de atraso que los de gracia, ONU ligada, activa y existente en SmartOLT, sin orden en cola y sin promesa de pago vigente. Al registrar un pago que cubre lo vencido se reconecta solo **si el último corte fue automático** (un corte manual nunca se revierte solo); si el corte seguía en cola, se anula.

**Mensualidad:** la del mes M vence el "día de pago" (1–28) de M y se genera solo si la fecha de alta es anterior o igual al vencimiento. Una mensualidad cancelada no se vuelve a generar. Meses pasados: `php artisan cobros:generar-cargos --mes=2026-10`.

## Comandos

```bash
php artisan operador:crear "Nombre" correo@x.mx [--rol=operador]   # admin por defecto; también reactiva/recupera acceso
php artisan smartolt:sincronizar [--cambios|--estados] [--forzar]
php artisan cobros:generar-cargos [--mes=AAAA-MM]
php artisan cortes:automaticos --prueba        # muestra a quién cortaría, sin cortar
php artisan cortes:automaticos                 # corta (requiere COBROS_CORTE_AUTOMATICO=true)
php artisan clientes:importar archivo.csv [--prueba]
```

**CSV de clientes** (UTF-8, separador `,` o `;`; ejemplo en `deploy/clientes-ejemplo.csv`):

```
numero,nombre,telefono,email,direccion,mensualidad,dia_pago,fecha_alta,onu_sn,notas
```

Obligatorios: `nombre` y `mensualidad`. Fechas `AAAA-MM-DD` o `DD/MM/AAAA`. `onu_sn` liga por número de serie. Si el `numero` ya existe, se actualiza en vez de duplicar. Usa `--prueba` primero.

**Antes del primer cobro real:** el primer `cobros:generar-cargos` cobra el mes completo a todos. Registra como pagado a quien ya pagó ese mes en el sistema anterior y captura deudas previas como cargo extra "Saldo anterior".

## Instalación en el VPS (producción)

Resumen para Ubuntu + Nginx + PHP-FPM + MySQL detrás de Cloudflare:

1. Instalar PHP 8.2+ (extensiones: mbstring, xml, curl, mysql, zip, bcmath, intl), Composer, MySQL, Nginx, Supervisor.
2. Clonar en `/var/www/wisp-cortes` y:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   # editar .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://…, DB_*, SESSION_SECURE_COOKIE=true
   php artisan migrate --force
   php artisan operador:crear "Admin" admin@empresa.mx
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   sudo chown -R www-data:www-data storage bootstrap/cache
   ```
3. Nginx con `root /var/www/wisp-cortes/public;` y `try_files $uri $uri/ /index.php?$query_string;`.
4. Cola: copiar `deploy/supervisor-wisp-cortes.conf` a `/etc/supervisor/conf.d/` y `sudo supervisorctl reread && sudo supervisorctl update`.
5. Cron (`crontab -e -u www-data`):
   ```
   * * * * * cd /var/www/wisp-cortes && php artisan schedule:run >> /dev/null 2>&1
   ```
6. Cloudflare con SSL "Full (strict)" y respaldos diarios de MySQL (`mysqldump`) fuera del servidor.
7. Probar SmartOLT con una ONU real (ver [SmartOLT](#smartolt)) y solo entonces `COBROS_CORTE_AUTOMATICO=true` + `php artisan config:cache`.

Al actualizar el código: `git pull && composer install --no-dev && php artisan migrate --force && php artisan optimize && sudo supervisorctl restart wisp-cortes-worker:*`.

## Pruebas

```bash
php artisan test
```

56 pruebas (SQLite en memoria, sin tocar SmartOLT: las llamadas se simulan con `Http::fake`). Cubren clientes, cobros, cortes automáticos, roles, usuarios, fallas, ONUs, configuración, inicio, modales y paginación.

## Estructura del código

```
app/
  Console/Commands/     smartolt:sincronizar, cobros:generar-cargos, cortes:automaticos, clientes:importar, operador:crear
  Enums/                Rol, TipoAccion, EstadoAccion, DecisionCorte, EstadoFalla, MetodoPago
  Events/ Listeners/    PagoRegistrado → ReconectarAlPagar
  Http/Controllers/     Controladores delgados (Inicio, Cliente, Pago, Cargo, Adeudo, Falla, Onu, AccionServicio, Usuario, Configuracion…)
  Http/Requests/        Validación de cada formulario
  Jobs/                 CambiarEstadoServicio (habla con SmartOLT vía la cola)
  Models/               Cliente, Onu, Cargo, Pago, Falla, AccionServicio, User, Ajuste
  Services/
    SmartOltService       único punto de contacto HTTP con SmartOLT
    ConfiguracionSmartOlt panel > .env, token cifrado
    ProgramadorCortes     registra en bitácora y encola (lotes de 50)
    PoliticaCortes        regla del corte automático
    GeneradorCargos       mensualidades
    EstadoCuenta          saldo, vencido, días de atraso (FIFO)
    ResumenInicio         contadores de Inicio
config/smartolt.php, config/cobros.php
database/seeders/       datos de prueba
deploy/                 Supervisor y CSV de ejemplo
lang/es/                traducciones
capturas/               capturas de pantalla para la propuesta
```

## Pendientes conocidos

- **Verificar rutas de SmartOLT** con la cuenta real y activar el corte automático.
- **Seguridad antes de producción:** aceptar `X-Forwarded-For` solo de IPs de Cloudflare (hoy `trustProxies('*')`); freno de emergencia si el corte del día es anormalmente grande; compilar Tailwind con Vite en lugar del CDN y agregar cabeceras CSP/HSTS.
- **Menú en celular:** con 10 opciones se desborda; falta menú desplegable.
- **Carrera pago/corte:** si un pago se registra justo mientras el job llama a SmartOLT, el corte se aplica; hay que reconectar a mano (se ve en la Bitácora).
- **Órdenes fallidas:** no hay botón "Reintentar" ni aviso por correo/WhatsApp.
- `onus.cliente_externo_id` no se usa todavía (pensado para ligar con la base externa).
- Fuera de alcance (fases siguientes): MikroTik, notificaciones a clientes, pagos en línea, CFDI, reportes, migración desde WispHub.
