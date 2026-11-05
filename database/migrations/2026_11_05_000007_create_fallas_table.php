<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fallas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->dateTime('reportada_el');
            $table->text('descripcion');
            $table->string('estado', 20)->index();      // abierta | en_proceso | resuelta
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            // Estado de la ONU en el momento del reporte (Online, LOS…), para diagnóstico
            $table->string('estado_red_al_reportar')->nullable();
            $table->text('solucion')->nullable();
            $table->dateTime('resuelta_el')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'reportada_el']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fallas');
    }
};
