<?php

namespace App\Console\Commands;

use App\Http\Controllers\PainelController;
use App\Services\Licenca;
use Illuminate\Console\Command;

class LicencaSinal extends Command
{
    protected $signature = 'nflog:sinal';
    protected $description = 'Sinal de vida para a central de licenças, com os problemas do coletor (a cada 5 min)';

    public function handle(Licenca $licenca, PainelController $painel): int
    {
        [$problemas, $operando] = $painel->problemas();
        $licenca->sinal($problemas, $operando);
        return self::SUCCESS;
    }
}
