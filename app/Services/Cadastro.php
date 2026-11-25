<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Services\Conectores\Conector;
use App\Services\Conectores\Nfc;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Qualificação do titular no relatório: busca no sistema de gestão do
 * provedor o cadastro do login PPPoE identificado.
 *
 * Só quando a consulta é REGISTRADA e só para resultado "identificado":
 * pré-visualização e resultado ambíguo não puxam dado pessoal de ninguém
 * (LGPD, art. 6º, III -- necessidade). O que voltar fica gravado no
 * resultado da consulta, como estava no momento do registro.
 */
class Cadastro
{
    public function conector(): ?Conector
    {
        $g = Configuracao::obterJson('gestao');
        if (($g['tipo'] ?? 'nenhum') !== 'nfc' || empty($g['url']) || empty($g['token'])) return null;
        return new Nfc($g['url'], Crypt::decryptString($g['token']));
    }

    public function completar(array $resultado, Carbon $momento): array
    {
        if ($resultado['conclusao'] !== 'identificado') return $resultado;
        $conector = $this->conector();
        if (!$conector) return $resultado;

        $logins = collect($resultado['candidatos'] ?? [])->pluck('sessao.login')
            ->push($resultado['ip_dedicado']['login'] ?? null)
            ->filter()->unique()->values();

        $cadastro = ['fonte' => $conector->nome(), 'consultado_em' => now()->toIso8601String(), 'por_login' => [], 'erro' => null];
        foreach ($logins as $login) {
            try {
                $cadastro['por_login'][$login] = $conector->buscar($login, $momento);
            } catch (RuntimeException $e) {
                $cadastro['erro'] = $e->getMessage();
            }
        }
        $resultado['cadastro'] = $cadastro;
        return $resultado;
    }
}
