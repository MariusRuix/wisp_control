@php($campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none')

<section class="mt-4 rounded-lg border bg-white p-5" id="fallas">
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Reportes de fallas</h2>

    @unless ($cliente->trashed())
        <form method="POST" action="{{ route('fallas.store', $cliente) }}" class="mb-5 rounded-md border border-amber-200 bg-amber-50/50 p-4">
            @csrf
            <h3 class="mb-3 text-sm font-semibold text-amber-900">Registrar falla</h3>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block text-sm sm:col-span-3"><span class="mb-1 block">¿Qué reporta el cliente? *</span>
                    <textarea name="descripcion" rows="2" required maxlength="5000" placeholder="Sin internet desde la mañana, luz roja en el módem…"
                              class="{{ $campo }}">{{ old('descripcion') }}</textarea>
                </label>
                <label class="block text-sm"><span class="mb-1 block">Fecha y hora *</span>
                    <input type="datetime-local" name="reportada_el" required max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('reportada_el', now()->format('Y-m-d\TH:i')) }}" class="{{ $campo }}">
                </label>
                <label class="block text-sm"><span class="mb-1 block">Quién la atiende</span>
                    <select name="asignado_a" class="{{ $campo }}">
                        <option value="">— Sin asignar —</option>
                        @foreach ($operadores as $op)
                            <option value="{{ $op->id }}" @selected((string) old('asignado_a') === (string) $op->id)>{{ $op->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Registrar</button>
                </div>
            </div>
            @if ($cliente->onu)
                <p class="mt-2 text-xs text-slate-500">Se guardará el estado actual de la ONU: <b>{{ $cliente->onu->estado_red ?: 'N/D' }}</b>.</p>
            @endif
        </form>
    @endunless

    @if ($fallas->isEmpty())
        <p class="text-sm text-slate-500">Sin fallas reportadas.</p>
    @else
        <ul class="divide-y">
            @foreach ($fallas as $f)
                <li class="py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="rounded-full px-2 py-0.5 {{ $f->estado->color() }}">{{ $f->estado->etiqueta() }}</span>
                                <span>{{ $f->reportada_el->format('d/m/Y H:i') }}</span>
                                @if ($f->estado_red_al_reportar)<span>· ONU: {{ $f->estado_red_al_reportar }}</span>@endif
                                <span>· Atiende: {{ $f->asignado?->name ?? 'sin asignar' }}</span>
                                <span>· Registró: {{ $f->creador?->name ?? '—' }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm">{{ $f->descripcion }}</p>
                            @if ($f->solucion)
                                <p class="mt-1 whitespace-pre-line text-sm text-emerald-800">
                                    <b>Solución{{ $f->resuelta_el ? ' ('.$f->resuelta_el->format('d/m/Y H:i').')' : '' }}:</b> {{ $f->solucion }}
                                </p>
                            @endif
                        </div>
                        <a href="{{ route('fallas.edit', [$f, 'volver' => 'cliente']) }}" class="rounded-md border bg-white px-3 py-1 text-xs hover:bg-slate-50">
                            {{ $f->estaResuelta() ? 'Ver / reabrir' : 'Actualizar' }}
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-3">{{ $fallas->links('vendor.pagination.wisp', ['ancla' => 'fallas']) }}</div>
    @endif
</section>
