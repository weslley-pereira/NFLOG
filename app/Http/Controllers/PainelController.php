<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Consulta;
use App\Models\SessaoPppoe;
use App\Services\ConsultaIp;
use App\Services\Integridade;
use App\Services\Licenca;
use App\Services\SessoesPppoe;
use App\Services\VictoriaLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** Saúde da coleta: o que tem que estar chegando para um ofício ter resposta. */
class PainelController extends Controller
{
    public function saude(VictoriaLogs $vl, Integridade $integridade, Licenca $licenca): JsonResponse
    {
        $vlOk = $vl->saudavel();
        $ultimo = function (string $app) use ($vl, $vlOk): ?string {
            if (!$vlOk) return null;
            $l = $vl->consultar('app_name:' . VictoriaLogs::frase($app) . ' | sort by (_time desc) | limit 1', now()->subDays(2), now(), 1);
            return isset($l[0]['_time']) ? Carbon::parse($l[0]['_time'])->toIso8601String() : null;
        };
        $natHora = 0;
        if ($vlOk) {
            $s = $vl->consultar('app_name:' . VictoriaLogs::frase(ConsultaIp::NAT_APP) . ' | stats count() n', now()->subHour(), now(), 1);
            $natHora = (int) ($s[0]['n'] ?? 0);
        }

        $base = rtrim(config('nflog.logs_base'), '/');
        $manifestos = glob("$base/manifestos/????-??-??.txt") ?: [];
        sort($manifestos);
        $ultimoDia = $manifestos ? basename(end($manifestos), '.txt') : null;

        $empresa = Configuracao::empresa();
        return response()->json([
            'victorialogs' => $vlOk,
            'nat' => ['ultimo_evento' => $ultimo(ConsultaIp::NAT_APP), 'eventos_ultima_hora' => $natHora],
            'pppoe' => [
                'ultimo_evento' => $ultimo(SessoesPppoe::TOPICOS),
                'sessoes_abertas' => SessaoPppoe::whereNull('fim')->where('ip', '!=', '')->count(),
                'sessoes_total' => SessaoPppoe::count(),
            ],
            'fechamento' => $ultimoDia ? $integridade->verificarDia($ultimoDia, []) : null,
            'disco' => is_dir($base) ? ['livre' => disk_free_space($base), 'total' => disk_total_space($base)] : null,
            'empresa_configurada' => (bool) ($empresa['razao_social'] && $empresa['cnpj']),
            'consultas' => Consulta::count(),
            'licenca' => array_intersect_key($licenca->estado(), array_flip(['situacao', 'mensagem'])),
        ]);
    }

    /**
     * Problemas da coleta em frases curtas (sinal para a central de licenças)
     * e se o essencial está operando. Barato: olha arquivos, não pesquisa.
     *
     * @return array{0: string[], 1: bool}
     */
    public function problemas(): array
    {
        $problemas = [];
        $operando = true;
        if (!app(VictoriaLogs::class)->saudavel()) {
            $problemas[] = 'Pesquisa (VictoriaLogs) fora do ar';
            $operando = false;
        }
        $base = rtrim(config('nflog.logs_base'), '/');
        foreach (glob("$base/arquivo/nat-*", GLOB_ONLYDIR) ?: [] as $pasta) {
            $hoje = "$pasta/" . now()->format('Y-m-d') . '.log';
            $minutos = is_file($hoje) ? intdiv(time() - filemtime($hoje), 60) : null;
            // Pasta sem nada há 7 dias = roteador desativado, não é problema.
            if (($minutos === null || $minutos > 10) && (glob("$pasta/*") ?: []) && max(array_map('filemtime', glob("$pasta/*"))) > time() - 7 * 86400) {
                $problemas[] = 'Log de NAT parado (' . substr(basename($pasta), 4) . ')';
                $operando = false;
            }
        }
        $ontem = now()->subDay()->format('Y-m-d');
        if ((int) now()->format('H') >= 2 && (glob("$base/arquivo/*/$ontem.log*") ?: []) && !is_file("$base/manifestos/$ontem.txt.sig")) {
            $problemas[] = "Fechamento de $ontem não saiu";
        }
        if (is_dir($base) && disk_free_space($base) / max(1, disk_total_space($base)) < 0.10) {
            $problemas[] = 'Disco dos registros com menos de 10% livre';
        }
        return [$problemas, $operando];
    }
}
