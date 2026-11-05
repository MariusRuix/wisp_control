<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            // Primer día del mes cobrado. Solo mensualidades; null en cargos extra.
            $table->date('periodo')->nullable();
            $table->string('concepto');
            $table->decimal('monto', 10, 2);
            $table->date('vence_el')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = automático
            $table->timestamp('cancelado_en')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamps();

            // Una sola mensualidad por cliente y mes (los extras llevan periodo null)
            $table->unique(['cliente_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
