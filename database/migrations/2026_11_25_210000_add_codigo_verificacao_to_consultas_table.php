<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Código impresso no PDF (e no QR) para quem recebe o relatório conferir a
// autenticidade na página pública /verificar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('codigo_verificacao', 24)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropUnique(['codigo_verificacao']);
            $table->dropColumn('codigo_verificacao');
        });
    }
};
