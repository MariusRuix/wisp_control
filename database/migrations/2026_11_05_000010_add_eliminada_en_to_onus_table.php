<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table): void {
            // Fecha en que la sincronización completa dejó de encontrarla en SmartOLT.
            // null = existe. Se limpia sola si la ONU vuelve a aparecer.
            $table->timestamp('eliminada_en')->nullable()->after('sincronizado_en')->index();
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table): void {
            $table->dropIndex(['eliminada_en']);
            $table->dropColumn('eliminada_en');
        });
    }
};
