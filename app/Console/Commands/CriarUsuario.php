<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Primeiro acesso: o instalador cria o administrador e mostra a senha uma vez. */
class CriarUsuario extends Command
{
    protected $signature = 'nflog:usuario {email} {nome} {--senha= : sem ela, gera uma aleatória e mostra}';
    protected $description = 'Cria (ou redefine a senha de) um usuário do painel';

    public function handle(): int
    {
        $senha = $this->option('senha') ?: Str::password(16, symbols: false);
        User::updateOrCreate(
            ['email' => strtolower($this->argument('email'))],
            ['name' => $this->argument('nome'), 'password' => Hash::make($senha)],
        );
        $this->info('Usuário ' . $this->argument('email') . ' pronto.');
        if (!$this->option('senha')) {
            $this->line("Senha: $senha  (anote agora; ela não fica guardada em texto)");
        }
        return self::SUCCESS;
    }
}
