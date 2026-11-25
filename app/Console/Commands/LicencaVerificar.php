<?php

namespace App\Console\Commands;

use App\Services\Licenca;
use Illuminate\Console\Command;

class LicencaVerificar extends Command
{
    protected $signature = 'nflog:licenca {--chave= : grava esta chave antes de verificar}';
    protected $description = 'Confere a licença no nf-licencas (agendado todo dia)';

    public function handle(Licenca $licenca): int
    {
        if ($this->option('chave')) $licenca->definirChave($this->option('chave'));
        $estado = $licenca->verificar();
        $this->line("{$estado['situacao']}: {$estado['mensagem']}" . ($estado['erro'] ? " (última tentativa: {$estado['erro']})" : ''));
        return self::SUCCESS;
    }
}
