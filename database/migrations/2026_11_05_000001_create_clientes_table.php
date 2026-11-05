<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            // Número de cliente / contrato (sirve para ligar con la BD externa)
            $table->string('numero', 50)->nullable()->unique();
            $table->string('nombre');
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->decimal('mensualidad', 10, 2);
            $table->unsignedTinyInteger('dia_pago');   // 1..28
            $table->date('fecha_alta');
            // Una ONU solo puede pertenecer a un cliente
            $table->foreignId('onu_id')->nullable()->unique()->constrained('onus')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->softDeletes();                     // deleted_at = fecha de baja
            $table->timestamps();

            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
