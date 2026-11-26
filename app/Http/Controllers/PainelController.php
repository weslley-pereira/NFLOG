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
use Illuminate\Support\Facades\Cache;

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

    /** Séries dos gráficos do painel (cache de 60 s: pesquisa + leitura de pastas). */
    public function graficos(VictoriaLogs $vl): JsonResponse
    {
        return response()->json(Cache::remember('painel_graficos', 60, fn () => $this->montarGraficos($vl)));
    }

    private function montarGraficos(VictoriaLogs $vl): array
    {
        $tz = config('app.timezone');
        $chaveHora = fn (Carbon $t) => $t->copy()->setTimezone($tz)->format('Y-m-d H');

        // 24 h, hora a hora (a hora atual em andamento por último).
        $inicio24 = now()->startOfHour()->subHours(23);
        $horas = collect(range(0, 23))->map(fn ($i) => $inicio24->copy()->addHours($i));

        $nat = null;
        if ($vl->saudavel()) {
            $porHora = [];
            foreach ($vl->consultar('app_name:' . VictoriaLogs::frase(ConsultaIp::NAT_APP) . ' | stats by (_time:1h) count() n', $inicio24, now(), 100) as $l) {
                if (isset($l['_time'])) $porHora[$chaveHora(Carbon::parse($l['_time']))] = (int) ($l['n'] ?? 0);
            }
            $nat = $horas->map(fn ($h) => $porHora[$chaveHora($h)] ?? 0)->all();
        }

        $logins = SessaoPppoe::where('origem_inicio', 'syslog')->where('inicio', '>=', $inicio24)->pluck('inicio')
            ->countBy(fn ($t) => $chaveHora($t));
        $logouts = SessaoPppoe::where('origem_fim', 'syslog')->where('fim', '>=', $inicio24)->pluck('fim')
            ->countBy(fn ($t) => $chaveHora($t));

        // Conectados hora a hora, 7 dias -- só desde que o NF Log começou a
        // registrar (antes disso a contagem sairia artificialmente baixa).
        $desde = SessaoPppoe::min('created_at');
        $inicio7 = now()->startOfHour()->subHours(167);
        if ($desde && Carbon::parse($desde)->gt($inicio7)) $inicio7 = Carbon::parse($desde)->startOfHour()->addHour();
        $sessoes = SessaoPppoe::where('ip', '!=', '')->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>=', $inicio7))
            ->where('inicio', '<=', now())->get(['inicio', 'fim'])
            ->map(fn ($s) => [$s->inicio->getTimestamp(), $s->fim?->getTimestamp()]);
        $conectados = [];
        for ($t = $inicio7->copy(); $t->lte(now()); $t->addHour()) {
            $ts = $t->getTimestamp();
            $conectados[] = ['hora' => $t->toIso8601String(),
                'n' => $sessoes->filter(fn ($s) => $s[0] <= $ts && ($s[1] === null || $s[1] > $ts))->count()];
        }

        // Volume guardado por dia (arquivo bruto já compactado) e fechamentos.
        $base = rtrim(config('nflog.logs_base'), '/');
        $volume = [];
        $fechamentos = [];
        foreach (range(30, 1) as $atras) {
            $dia = now()->subDays($atras)->format('Y-m-d');
            $nat_ = $sys = 0;
            foreach (glob("$base/arquivo/*/$dia.log.zst") ?: [] as $f) {
                str_starts_with(basename(dirname($f)), 'nat-') ? $nat_ += filesize($f) : $sys += filesize($f);
            }
            $volume[] = ['dia' => $dia, 'nat' => $nat_, 'syslog' => $sys];
            if ($atras <= 13) {
                $temArquivo = (bool) (glob("$base/arquivo/*/$dia.log*") ?: []);
                $fechamentos[] = ['dia' => $dia, 'situacao' => is_file("$base/manifestos/$dia.txt.sig") ? 'assinado' : ($temArquivo ? 'nao_fechado' : 'sem_coleta')];
            }
        }
        $fechamentos[] = ['dia' => now()->format('Y-m-d'), 'situacao' => 'andamento'];

        // Ritmo do disco: uma foto do espaço livre por dia (guardada aqui mesmo).
        $livre = is_dir($base) ? disk_free_space($base) : null;
        $historico = Configuracao::obterJson('disco_historico');
        if ($livre !== null && !isset($historico[now()->format('Y-m-d')])) {
            $historico[now()->format('Y-m-d')] = $livre;
            $historico = array_slice($historico, -30, null, true);
            Configuracao::definirJson('disco_historico', $historico);
        }
        $diasRestantes = null;
        $porDia = null;
        if (count($historico) >= 3) {
            $datas = array_keys($historico);
            $dias = max(1, Carbon::parse(reset($datas))->diffInDays(Carbon::parse(end($datas))));
            $porDia = (reset($historico) - end($historico)) / $dias;
            if ($porDia > 0 && $livre) $diasRestantes = (int) floor($livre / $porDia);
        }

        $meses = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $porMes = Consulta::where('created_at', '>=', $meses->first())->pluck('created_at')->countBy(fn ($t) => $t->format('Y-m'));

        return [
            'horas' => $horas->map->toIso8601String()->all(),
            'nat' => $nat,
            'logins' => $horas->map(fn ($h) => $logins[$chaveHora($h)] ?? 0)->all(),
            'logouts' => $horas->map(fn ($h) => $logouts[$chaveHora($h)] ?? 0)->all(),
            'conectados' => $conectados,
            'volume' => $volume,
            'fechamentos' => $fechamentos,
            'disco' => ['livre' => $livre, 'gasto_por_dia' => $porDia, 'dias_restantes' => $diasRestantes, 'dias_medidos' => count($historico)],
            'consultas' => $meses->map(fn ($m) => ['mes' => $m->format('Y-m'), 'n' => $porMes[$m->format('Y-m')] ?? 0])->all(),
        ];
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
