<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Recuperação: quem perdeu o celular pede a quem tem acesso ao servidor. */
class DesativarDoisFatores extends Command
{
    protected $signature = 'nflog:2fa-desativar {email}';
    protected $description = 'Desativa a verificação em duas etapas de um usuário (celular perdido)';

    public function handle(): int
    {
        $usuario = User::where('email', strtolower($this->argument('email')))->first();
        if (!$usuario) {
            $this->error('Usuário não encontrado.');
            return self::FAILURE;
        }
        $usuario->forceFill(['totp_segredo' => null, 'totp_ativo' => false, 'totp_ultimo_passo' => null])->save();
        $this->info("Verificação em duas etapas desativada para {$usuario->email}. Peça para ativar de novo em Minha conta.");
        return self::SUCCESS;
    }
}
