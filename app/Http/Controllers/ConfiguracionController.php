<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\SmartOltException;
use App\Http\Requests\ConfiguracionSmartOltRequest;
use App\Models\Onu;
use App\Services\ConfiguracionSmartOlt;
use App\Services\SmartOltService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Configuración › SmartOLT (solo Administrador). */
final class ConfiguracionController extends Controller
{
    public function smartolt(ConfiguracionSmartOlt $cfg): View
    {
        return view('configuracion.smartolt', [
            'cfg' => $cfg,
            'onus' => Onu::count(),
            'ultimaSync' => Onu::max('sincronizado_en'),
        ]);
    }

    public function guardar(ConfiguracionSmartOltRequest $request, ConfiguracionSmartOlt $cfg): RedirectResponse
    {
        $cfg->guardar($request->validated(), $request->user());

        Log::info('Configuración SmartOLT actualizada', [
            'usuario' => $request->user()->email,
            'cambio_token' => $request->filled('token'),
        ]);

        if ($request->boolean('probar')) {
            return $this->probar(app(SmartOltService::class), 'Guardado. ');
        }

        return back()->with('ok', 'Configuración de SmartOLT guardada.');
    }

    public function probar(SmartOltService $smartOlt, string $prefijo = ''): RedirectResponse
    {
        try {
            $r = $smartOlt->probarConexion();

            return redirect()->route('configuracion.smartolt')
                ->with('ok', $prefijo."Conexión correcta con SmartOLT: {$r['olts']} OLT(s), respondió en {$r['ms']} ms.");
        } catch (SmartOltException $e) {
            return redirect()->route('configuracion.smartolt')
                ->with('aviso', $prefijo.'No se pudo conectar: '.$e->getMessage());
        }
    }

    public function sincronizar(): RedirectResponse
    {
        Artisan::queue('smartolt:sincronizar')->onQueue('smartolt');

        return back()->with('ok', 'Sincronización enviada a la cola. En unos minutos se actualiza la lista de ONUs.');
    }

    public function restablecer(ConfiguracionSmartOlt $cfg): RedirectResponse
    {
        $cfg->restablecer();

        return back()->with('aviso', 'Se borró lo guardado en el panel: ahora se usa lo que diga el .env.');
    }
}
