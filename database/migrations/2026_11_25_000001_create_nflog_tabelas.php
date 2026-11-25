<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dados da empresa, roteadores, cursores dos importadores.
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave', 100)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        // Quem esteve com cada IP privado, montado do syslog do Mikrotik
        // ("<login> logged in, <IP> from <MAC>" / "logged out") e das fotos
        // das sessões ativas pela API. É o registro de conexão (art. 13).
        Schema::create('sessoes_pppoe', function (Blueprint $table) {
            $table->id();
            $table->string('roteador', 45);
            $table->string('login', 150);
            $table->string('ip', 45);
            $table->string('mac', 17)->nullable();
            $table->dateTime('inicio');
            $table->dateTime('fim')->nullable();
            // syslog | api (foto das ativas pela API, início = agora - uptime)
            $table->string('origem_inicio', 10);
            // syslog | sucessora (nova sessão do mesmo login sem logout antes)
            $table->string('origem_fim', 10)->nullable();
            $table->timestamps();

            $table->index(['ip', 'inicio']);
            $table->index(['login', 'fim']);
        });

        // Cada consulta (ofício) feita no painel, com o relatório gerado.
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->string('autoridade', 200)->nullable();
            $table->string('documento', 120)->nullable();   // nº do ofício
            $table->string('processo', 120)->nullable();
            $table->date('recebido_em')->nullable();
            $table->string('ip', 45);
            $table->unsignedInteger('porta')->nullable();
            $table->string('protocolo', 5)->nullable();
            $table->dateTime('momento');                    // no fuso do servidor
            $table->string('momento_informado', 60);        // como veio no ofício
            $table->string('fuso_informado', 40);
            $table->json('resultado');
            $table->string('conclusao', 30);                // identificado | ambiguo | sem_registro | ...
            $table->string('relatorio_arquivo')->nullable();
            $table->string('relatorio_sha256', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
        Schema::dropIfExists('sessoes_pppoe');
        Schema::dropIfExists('configuracoes');
    }
};
