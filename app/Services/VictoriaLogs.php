<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo da API de consulta do VictoriaLogs (LogsQL).
 */
class VictoriaLogs
{
    /**
     * @return array<int, array<string, mixed>> uma entrada por linha de log,
     *         com _time, _msg e os campos que o VictoriaLogs extraiu
     */
    public function consultar(string $logsql, CarbonInterface $de, CarbonInterface $ate, int $limite = 5000): array
    {
        $config = config('nflog.victorialogs');
        $requisicao = Http::timeout(60)->asForm();
        if ($config['usuario']) {
            $requisicao = $requisicao->withBasicAuth($config['usuario'], (string) $config['senha']);
        }

        $resposta = $requisicao->post(rtrim($config['url'], '/') . '/select/logsql/query', [
            'query' => $logsql,
            'start' => $de->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'end' => $ate->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'limit' => $limite,
        ]);
        if (!$resposta->successful()) {
            throw new RuntimeException('VictoriaLogs respondeu HTTP ' . $resposta->status() . ': ' . mb_substr($resposta->body(), 0, 300));
        }

        $linhas = [];
        foreach (preg_split('/\r?\n/', trim($resposta->body())) as $linha) {
            if ($linha === '') continue;
            $dados = json_decode($linha, true);
            if (is_array($dados)) $linhas[] = $dados;
        }
        return $linhas;
    }

    public function saudavel(): bool
    {
        try {
            $config = config('nflog.victorialogs');
            $requisicao = Http::timeout(5);
            if ($config['usuario']) {
                $requisicao = $requisicao->withBasicAuth($config['usuario'], (string) $config['senha']);
            }
            return $requisicao->get(rtrim($config['url'], '/') . '/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Termo literal para LogsQL (entre aspas, com escape). */
    public static function frase(string $texto): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $texto) . '"';
    }
}
