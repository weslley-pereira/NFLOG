<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Consulta;
use App\Models\SessaoPppoe;
use App\Services\ConsultaIp;
use App\Services\Integridade;
use App\Services\SessoesPppoe;
use App\Services\VictoriaLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** Saúde da coleta: o que tem que estar chegando para um ofício ter resposta. */
class PainelController extends Controller
{
    public function saude(VictoriaLogs $vl, Integridade $integridade): JsonResponse
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
        ]);
    }
}
