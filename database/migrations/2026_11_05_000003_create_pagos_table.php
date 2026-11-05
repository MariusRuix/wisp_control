<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->date('fecha')->index();
            $table->string('metodo', 20);
            $table->string('referencia', 100)->nullable();
            $table->string('notas')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Los pagos no se borran: se cancelan y queda constancia
            $table->timestamp('cancelado_en')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
