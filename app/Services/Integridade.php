<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Cadeia de custódia dos arquivos usados numa consulta: confere a
 * assinatura do manifesto diário (ssh-keygen -Y verify) e o sha256 de cada
 * arquivo contra o manifesto. O manifesto de um dia traz o sha256 do
 * anterior, então alterar um dia quebra a corrente dali pra frente.
 */
class Integridade
{
    /**
     * @param string[] $pastas subpastas de arquivo/ (ex.: "nat-10.249.0.1", "10.249.0.1")
     * @return array{dia:string, situacao:string, detalhe:string, manifesto_sha256:?string, anterior:?string, arquivos:array}
     */
    public function verificarDia(string $dia, array $pastas): array
    {
        $base = rtrim(config('nflog.logs_base'), '/');
        $resultado = ['dia' => $dia, 'situacao' => '', 'detalhe' => '', 'manifesto_sha256' => null, 'anterior' => null, 'arquivos' => []];

        if ($dia >= now()->format('Y-m-d')) {
            $resultado['situacao'] = 'aberto';
            $resultado['detalhe'] = 'Dia ainda em andamento: os arquivos são fechados, resumidos (SHA-256) e assinados no fechamento diário, após a meia-noite.';
            return $resultado;
        }

        $manifesto = "$base/manifestos/$dia.txt";
        if (!is_file($manifesto) || !is_file("$manifesto.sig")) {
            $resultado['situacao'] = 'sem_manifesto';
            $resultado['detalhe'] = 'Manifesto do dia não encontrado no disco local.';
            return $resultado;
        }
        $resultado['manifesto_sha256'] = hash_file('sha256', $manifesto);

        $verificacao = Process::input(file_get_contents($manifesto))->run([
            'ssh-keygen', '-Y', 'verify', '-f', config('nflog.assinantes_permitidos'),
            '-I', config('nflog.assinatura_identidade'), '-n', config('nflog.assinatura_namespace'), '-s', "$manifesto.sig",
        ]);
        $assinaturaOk = $verificacao->successful();

        $hashes = [];
        foreach (file($manifesto, FILE_IGNORE_NEW_LINES) as $linha) {
            if (preg_match('/^anterior: (\S+)/', $linha, $m)) $resultado['anterior'] = $m[1];
            if (preg_match('/^([0-9a-f]{64})\s+\.\/(.+)$/', $linha, $m)) $hashes[$m[2]] = $m[1];
        }

        $todosOk = $assinaturaOk;
        foreach ($pastas as $pasta) {
            $relativo = "$pasta/$dia.log.zst";
            $esperado = $hashes[$relativo] ?? null;
            $caminho = "$base/arquivo/$relativo";
            $atual = is_file($caminho) ? hash_file('sha256', $caminho) : null;
            $situacao = match (true) {
                $esperado === null => 'fora_do_manifesto',
                $atual === null => 'so_na_copia_externa',
                $atual === $esperado => 'confere',
                default => 'divergente',
            };
            if (in_array($situacao, ['divergente', 'fora_do_manifesto'], true)) $todosOk = false;
            $resultado['arquivos'][] = ['arquivo' => $relativo, 'sha256' => $esperado ?? $atual, 'situacao' => $situacao];
        }

        $resultado['situacao'] = $todosOk ? 'integro' : 'falha';
        $resultado['detalhe'] = $assinaturaOk
            ? ($todosOk ? 'Assinatura do manifesto válida e arquivos conferem com o resumo registrado.' : 'Assinatura válida, mas há arquivo divergente ou fora do manifesto.')
            : 'ASSINATURA DO MANIFESTO INVÁLIDA.';
        return $resultado;
    }
}
