@if ($cliente->trashed())
    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">De baja</span>
@elseif (! $cliente->onu)
    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Sin ONU</span>
@elseif (! $cliente->onu->existeEnSmartOlt())
    <span class="whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">ONU eliminada</span>
@elseif ($cliente->onu->estaCortada())
    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Cortado</span>
@else
    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Activo</span>
@endif
