<?php

namespace App\Services\Conectores;

use Illuminate\Support\Carbon;

/**
 * Sistema de gestão do provedor (onde está o cadastro do assinante). Dado o
 * login PPPoE identificado e o instante, devolve quem é o titular.
 */
interface Conector
{
    /** Nome que aparece no relatório como fonte do cadastro. */
    public function nome(): string;

    /**
     * @return array<int, array{vigente_no_instante: ?bool, login_atual: bool,
     *   cliente: array, endereco: array, endereco_instalacao: ?array, contrato: array}>
     *
     * @throws \RuntimeException quando o sistema não responde ou recusa o acesso
     */
    public function buscar(string $login, Carbon $instante): array;
}
