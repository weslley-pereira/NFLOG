<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verificação em duas etapas (TOTP) no login do painel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_segredo')->nullable();            // cifrado (cast encrypted)
            $table->boolean('totp_ativo')->default(false);
            $table->unsignedBigInteger('totp_ultimo_passo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_segredo', 'totp_ativo', 'totp_ultimo_passo']);
        });
    }
};
