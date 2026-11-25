<?php

namespace App\Console\Commands;

use App\Services\FotoSessoesMikrotik;
use App\Services\SessoesPppoe;
use Illuminate\Console\Command;

class ImportarSessoes extends Command
{
    protected $signature = 'nflog:sessoes {--foto : também tira a foto das sessões ativas pela API do Mikrotik}';
    protected $description = 'Monta as sessões PPPoE a partir do syslog do Mikrotik (e da API, com --foto)';

    public function handle(SessoesPppoe $sessoes, FotoSessoesMikrotik $foto): int
    {
        $r = $sessoes->importar();
        if ($r['eventos']) {
            $this->info("syslog: {$r['eventos']} eventos, {$r['abertas']} sessões abertas, {$r['fechadas']} fechadas");
        }
        if ($this->option('foto')) {
            foreach ($foto->executar() as $roteador => $situacao) {
                $this->info("API $roteador: $situacao");
            }
        }
        return self::SUCCESS;
    }
}
