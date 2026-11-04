<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acciones_servicio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onu_id')->constrained('onus')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 20);            // cortar | reconectar
            $table->string('estado', 20)->index(); // en_cola | exito | fallido
            $table->string('motivo')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('ejecutada_en')->nullable();
            $table->timestamps();

            $table->index(['onu_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acciones_servicio');
    }
};
