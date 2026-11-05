<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Onu;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Importa clientes desde un CSV limpio (UTF-8, separador "," o ";").
 *
 * Encabezados (en cualquier orden; solo "nombre" y "mensualidad" son obligatorios):
 *   numero,nombre,telefono,email,direccion,mensualidad,dia_pago,fecha_alta,onu_sn,notas
 *
 * Si la fila trae "numero" y ya existe un cliente con ese número, se actualiza.
 *
 *   php artisan clientes:importar storage/app/clientes.csv --prueba
 *   php artisan clientes:importar storage/app/clientes.csv
 */
final class ImportarClientes extends Command
{
    protected $signature = 'clientes:importar {archivo} {--prueba : Solo valida, no guarda nada}';

    protected $description = 'Importa clientes desde un archivo CSV';

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');
        if (! is_readable($ruta)) {
            $this->error("No puedo leer {$ruta}");

            return self::FAILURE;
        }

        $fh = fopen($ruta, 'rb');
        $primera = (string) fgets($fh);
        $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        $encabezados = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $primera), $separador),
        );

        if (! in_array('nombre', $encabezados, true) || ! in_array('mensualidad', $encabezados, true)) {
            $this->error('El CSV debe tener al menos las columnas "nombre" y "mensualidad".');

            return self::FAILURE;
        }

        $creados = $actualizados = 0;
        $errores = [];
        $linea = 1;

        DB::beginTransaction();
        try {
            while (($fila = fgetcsv($fh, 0, $separador)) !== false) {
                $linea++;
                if ($fila === [null] || trim(implode('', $fila)) === '') {
                    continue;
                }

                $d = array_combine($encabezados, array_pad(array_slice($fila, 0, count($encabezados)), count($encabezados), null));
                $d = array_map(fn ($v) => $v === null || trim((string) $v) === '' ? null : trim((string) $v), $d);

                $resultado = $this->procesar($d);
                if (is_string($resultado)) {
                    $errores[] = "Línea {$linea}: {$resultado}";

                    continue;
                }

                $resultado ? $creados++ : $actualizados++;
            }

            $this->option('prueba') ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            fclose($fh);
        }

        foreach ($errores as $e) {
            $this->warn($e);
        }

        $modo = $this->option('prueba') ? ' (PRUEBA: no se guardó nada)' : '';
        $this->info("Nuevos: {$creados} · Actualizados: {$actualizados} · Con error: ".count($errores).$modo);

        return self::SUCCESS;
    }

    /**
     * @return bool|string true = creado, false = actualizado, string = error
     */
    private function procesar(array $d): bool|string
    {
        $existente = isset($d['numero']) ? Cliente::withTrashed()->where('numero', $d['numero'])->first() : null;

        $onuId = null;
        if (! empty($d['onu_sn'])) {
            $onuId = Onu::where('sn', $d['onu_sn'])->value('id');
            if ($onuId === null) {
                return "ONU {$d['onu_sn']} no existe (sincroniza SmartOLT primero)";
            }
            $ocupada = Cliente::where('onu_id', $onuId)->when($existente, fn ($q) => $q->whereKeyNot($existente->id))->exists();
            if ($ocupada) {
                return "ONU {$d['onu_sn']} ya está ligada a otro cliente";
            }
        }

        $datos = [
            'numero' => $d['numero'] ?? null,
            'nombre' => $d['nombre'] ?? null,
            'telefono' => $d['telefono'] ?? null,
            'email' => $d['email'] ?? null,
            'direccion' => $d['direccion'] ?? null,
            'mensualidad' => isset($d['mensualidad']) ? str_replace([',', '$', ' '], '', $d['mensualidad']) : null,
            'dia_pago' => $d['dia_pago'] ?? 1,
            'fecha_alta' => $this->fecha($d['fecha_alta'] ?? null),
            'onu_id' => $onuId,
            'notas' => $d['notas'] ?? null,
        ];

        $v = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'mensualidad' => ['required', 'numeric', 'min:0'],
            'dia_pago' => ['required', 'integer', 'between:1,28'],
            'fecha_alta' => ['required', 'date'],
            'email' => ['nullable', 'email'],
        ]);

        if ($v->fails()) {
            return implode(' ', $v->errors()->all());
        }

        if ($existente) {
            // No borra la ONU ya ligada si el CSV no trae una
            $existente->update(array_filter($datos, fn ($v, $k) => $k !== 'onu_id' || $v !== null, ARRAY_FILTER_USE_BOTH));

            return false;
        }

        Cliente::create($datos);

        return true;
    }

    /** Acepta 2026-11-05, 05/11/2026 o vacío (= hoy). */
    private function fecha(?string $valor): ?string
    {
        if ($valor === null) {
            return now()->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            try {
                $f = Carbon::createFromFormat($formato, $valor);
                if ($f !== false && $f->format($formato) === $valor) {
                    return $f->toDateString();
                }
            } catch (Throwable) {
                // probar el siguiente formato
            }
        }

        return $valor; // que la validación lo marque
    }
}
