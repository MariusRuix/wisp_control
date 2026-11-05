<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajustes editables desde el panel (tienen prioridad sobre el .env).
        Schema::create('ajustes', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 100)->unique();     // p. ej. smartolt.token
            $table->text('valor')->nullable();
            $table->boolean('cifrado')->default(false);  // true = valor cifrado con APP_KEY
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
