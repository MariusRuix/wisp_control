<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DecisionCorte;
use App\Enums\EstadoAccion;
use App\Enums\EstadoFalla;
use App\Models\AccionServicio;
use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Falla;
use App\Models\Onu;
use App\Models\Pago;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/** Contadores para la pantalla de inicio. Solo lectura. */
final class ResumenInicio
{
    public function __construct(private readonly PoliticaCortes $politica)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function calcular(User $usuario, ?CarbonImmutable $hoy = null): array
    {
        $hoy ??= CarbonImmutable::now();
        $inicioMes = $hoy->startOfMonth()->toDateString();
        $finMes = $hoy->endOfMonth()->toDateString();

        // Clientes
        $activos = Cliente::count();
        $nuevosMes = Cliente::whereDate('fecha_alta', '>=', $inicioMes)->count();
        $cortados = Cliente::whereHas('onu', fn ($q) => $q->where('estado_admin', 'Disabled'))->count();
        $sinOnu = Cliente::whereNull('onu_id')->count();

        // Cobranza: evaluación del corte automático (incluye vencido por cliente)
        $evaluacion = $this->politica->evaluar($hoy);
        $vencido = round((float) $evaluacion->sum(fn ($f) => $f['resumen']['vencido']), 2);
        $porDecision = $evaluacion->countBy(fn ($f) => $f['decision']->value);

        $cobradoHoy = (float) Pago::vigentes()->whereDate('fecha', $hoy->toDateString())->sum('monto');
        $pagosHoy = Pago::vigentes()->whereDate('fecha', $hoy->toDateString())->count();
        $cobradoMes = (float) Pago::vigentes()->whereDate('fecha', '>=', $inicioMes)->whereDate('fecha', '<=', $finMes)->sum('monto');
        $facturadoMes = (float) Cargo::vigentes()->whereDate('vence_el', '>=', $inicioMes)->whereDate('vence_el', '<=', $finMes)->sum('monto');

        // Red y soporte
        $fallasAbiertas = Falla::where('estado', EstadoFalla::Abierta->value)->count();
        $fallasEnProceso = Falla::where('estado', EstadoFalla::EnProceso->value)->count();
        $fallasMias = Falla::pendientes()->where('asignado_a', $usuario->id)->count();
        $onusProblema = Onu::query()->vigentes()->whereNotNull('estado_red')->where('estado_red', '!=', 'Online')
            ->where(fn ($w) => $w->whereNull('estado_admin')->orWhere('estado_admin', '!=', 'Disabled'))->count();
        $erroresSmartOlt = AccionServicio::where('estado', EstadoAccion::Fallido->value)
            ->where('created_at', '>=', $hoy->subDay())->count();
        $enCola = AccionServicio::where('estado', EstadoAccion::EnCola->value)->count();
        $ultimaSync = Onu::max('sincronizado_en');
        $onusEliminadas = Onu::whereNotNull('eliminada_en')->count();
        $clientesOnuEliminada = Cliente::whereHas('onu', fn ($q) => $q->whereNotNull('eliminada_en'))->count();

        return [
            'clientes' => compact('activos', 'nuevosMes', 'cortados', 'sinOnu'),
            'cobranza' => [
                'vencido' => $vencido,
                'deudores' => $evaluacion->count(),
                'seCortan' => $porDecision->get(DecisionCorte::Cortar->value, 0),
                'enGracia' => $porDecision->get(DecisionCorte::EnGracia->value, 0),
                'promesas' => $porDecision->get(DecisionCorte::Promesa->value, 0),
                'corteAutomatico' => (bool) config('cobros.corte_automatico'),
                'horaCorte' => (string) config('cobros.hora_corte'),
                'cobradoHoy' => $cobradoHoy,
                'pagosHoy' => $pagosHoy,
                'cobradoMes' => $cobradoMes,
                'facturadoMes' => $facturadoMes,
                'avanceMes' => $facturadoMes > 0 ? (int) min(100, round($cobradoMes / $facturadoMes * 100)) : null,
            ],
            'red' => [
                'fallasAbiertas' => $fallasAbiertas,
                'fallasEnProceso' => $fallasEnProceso,
                'fallasMias' => $fallasMias,
                'onusProblema' => $onusProblema,
                'erroresSmartOlt' => $erroresSmartOlt,
                'enCola' => $enCola,
                'onusEliminadas' => $onusEliminadas,
                'clientesOnuEliminada' => $clientesOnuEliminada,
                'ultimaSync' => $ultimaSync ? Carbon::parse($ultimaSync) : null,
                // Más de 2 horas sin sincronizar = algo anda mal (cola o token)
                'syncAtrasada' => $ultimaSync === null || Carbon::parse($ultimaSync)->lt($hoy->subHours(2)),
            ],
        ];
    }
}
