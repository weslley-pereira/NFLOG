<?php

namespace App\Services;

use App\Models\SessaoPppoe;
use Illuminate\Support\Carbon;

/**
 * Quem usava um IP público (e porta) num instante -- o que um ofício pede.
 *
 *   IP público + porta + instante
 *     → evento de NAT do CGNAT que cobre o instante (log de NAT por IPFIX:
 *       "abre"/"fecha" com IP:porta privado e público, sem destino)
 *     → IP privado
 *     → sessão PPPoE com esse IP no instante (syslog/API do Mikrotik)
 *     → login e MAC (o cliente sai do conector com o sistema de gestão)
 *
 * IP sem CGNAT (público dedicado ao assinante) vai direto na sessão PPPoE.
 */
class ConsultaIp
{
    public const NAT_APP = 'nat-cgnat';

    public function __construct(
        private readonly VictoriaLogs $vl,
        private readonly Integridade $integridade,
    ) {}

    public function buscar(string $ip, ?int $porta, Carbon $momento, ?string $protocolo): array
    {
        $tolerancia = config('nflog.tolerancia_segundos');
        $resultado = [
            'ip' => $ip, 'porta' => $porta, 'protocolo' => $protocolo,
            'momento' => $momento->toIso8601String(), 'tolerancia_segundos' => $tolerancia,
            'nat' => [], 'candidatos' => [], 'ip_dedicado' => null,
            'conclusao' => '', 'explicacao' => '', 'integridade' => [], 'consultado_em' => now()->toIso8601String(),
        ];
        $dias = [];
        $pastas = [];

        // IP público dedicado ao assinante (sem CGNAT): a sessão PPPoE já tem ele.
        if ($sessao = SessoesPppoe::noInstante($ip, $momento, $tolerancia)) {
            $resultado['ip_dedicado'] = $this->sessaoParaRelatorio($sessao);
            $this->anotarSessao($sessao, $dias, $pastas);
            $resultado['conclusao'] = 'identificado';
            $resultado['explicacao'] = 'O IP estava atribuído diretamente (sem CGNAT) à sessão PPPoE abaixo no instante pedido.';
            return $this->fechar($resultado, $dias, $pastas);
        }

        $pastas = array_merge($pastas, $this->pastasNat());

        // Sem log de NAT chegando no instante (coleta parada, fora da
        // retenção), não dá pra afirmar nada: uma sessão "sem fechamento"
        // pareceria cobrir o instante só porque o log parou.
        // Fato de poucos minutos atrás: o NAT chega com 1-2 min de atraso
        // (o nfacctd grava por minuto). Sem registros depois do instante,
        // ainda não dá para dizer nada.
        if ($momento->gt(now()->subMinutes(10)) && !$this->haDadosNat($momento->copy()->addSeconds($tolerancia), now())) {
            $resultado['conclusao'] = 'sem_dados';
            $resultado['explicacao'] = 'Os registros de NAT desse horário ainda estão chegando (atraso de 1 a 2 minutos). Consulte de novo em alguns minutos.';
            return $this->fechar($resultado, $dias, $pastas);
        }

        if (!$this->haDadosNat($momento)) {
            $resultado['conclusao'] = 'sem_dados';
            $resultado['explicacao'] = 'Não há registros de tradução de endereços (NAT) recebidos no minuto do instante pedido: a coleta estava interrompida ou o período está fora da retenção. Não é possível afirmar quem utilizava o endereço.';
            return $this->fechar($resultado, $dias, $pastas);
        }

        $eventos = $this->eventosNat($ip, $porta, $momento);
        foreach ($eventos as $e) {
            $dias[$e['quando']->format('Y-m-d')] = true;
        }

        if ($porta === null) {
            // Sem porta, o IP do CGNAT é compartilhado: só dá pra dizer quantos usavam.
            $privados = collect($eventos)->filter(fn ($e) => abs($e['quando']->diffInSeconds($momento)) <= 600)->pluck('priv_ip')->unique()->values();
            $resultado['conclusao'] = $privados->isEmpty() ? 'sem_registro' : 'sem_porta';
            $resultado['explicacao'] = $privados->isEmpty()
                ? 'Nenhum evento de NAT com esse IP público perto do instante pedido.'
                : "IP público compartilhado (CGNAT): {$privados->count()} endereços privados o usavam perto do instante. Sem a porta lógica de origem não é possível individualizar o usuário.";
            $resultado['privados_no_periodo'] = $privados->count();
            return $this->fechar($resultado, $dias, $pastas);
        }

        // Sessões de NAT (abre → fecha) por IP:porta privado e protocolo. O
        // RouterOS repete o evento de abertura da mesma conexão (mesma tupla,
        // segundos depois) antes do fechamento: é uma sessão só, do primeiro
        // "abre" até o "fecha".
        $sessoesNat = [];
        foreach (collect($eventos)->groupBy(fn ($e) => $e['proto'] . '|' . $e['priv']) as $grupo) {
            $aberta = null;
            foreach ($grupo->sortBy(fn ($e) => $e['quando']->getTimestamp()) as $e) {
                if ($e['ev'] === 'abre') {
                    $aberta ??= ['proto' => $e['proto'], 'priv' => $e['priv'], 'priv_ip' => $e['priv_ip'], 'abre' => $e['quando']];
                } elseif ($aberta) {
                    $sessoesNat[] = $aberta + ['fecha' => $e['quando']];
                    $aberta = null;
                }
            }
            if ($aberta) $sessoesNat[] = $aberta + ['fecha' => null];
        }

        // No resultado (e no relatório) só entra a sessão que cobre o instante:
        // as demais do mesmo IP:porta na janela são de outros horários (e, num
        // CGNAT não determinístico, de outros assinantes). Se nenhuma cobrir,
        // vão as próximas (±10 min), para conferir horário, fuso e protocolo.
        $linha = fn ($s, $cobre) => [
            'protocolo' => $s['proto'], 'privado' => $s['priv'], 'publico' => "$ip:$porta",
            'abre' => $s['abre']->toIso8601String(), 'fecha' => $s['fecha']?->toIso8601String(), 'cobre_instante' => $cobre,
        ];
        $proximas = [];
        foreach ($sessoesNat as $s) {
            $cobre = $s['abre']->copy()->subSeconds($tolerancia) <= $momento
                && ($s['fecha'] === null || $s['fecha']->copy()->addSeconds($tolerancia) >= $momento)
                && ($protocolo === null || $s['proto'] === $protocolo);
            if (!$cobre) {
                $perto = $s['abre']->lte($momento->copy()->addMinutes(10)) && ($s['fecha'] ?? $s['abre'])->gte($momento->copy()->subMinutes(10));
                if ($perto) $proximas[] = $linha($s, false);
                continue;
            }
            $resultado['nat'][] = $linha($s, true);

            $sessao = SessoesPppoe::noInstante($s['priv_ip'], $momento, $tolerancia);
            if ($sessao) $this->anotarSessao($sessao, $dias, $pastas);
            $resultado['candidatos'][] = [
                'privado' => $s['priv'], 'protocolo' => $s['proto'],
                'sessao' => $sessao ? $this->sessaoParaRelatorio($sessao) : null,
            ];
        }

        if (!$resultado['nat']) $resultado['nat'] = $proximas;
        $resultado['nat_omitidas'] = count($sessoesNat) - count($resultado['nat']);

        $logins = collect($resultado['candidatos'])->pluck('sessao.login')->filter()->unique();
        $privados = collect($resultado['candidatos'])->pluck('privado')->unique();
        [$resultado['conclusao'], $resultado['explicacao']] = match (true) {
            $privados->isEmpty() => ['sem_registro', 'Nenhuma sessão de NAT com esse IP e porta cobre o instante pedido'
                . ($protocolo ? " no protocolo " . strtoupper($protocolo) : '') . " (tolerância de ±{$tolerancia}s)."],
            $logins->count() === 1 => ['identificado', 'Uma única sessão de NAT cobre o instante, e o endereço privado dela estava com a sessão PPPoE abaixo.'],
            $logins->count() > 1 => ['ambiguo', "Mais de um usuário aparece no instante ({$logins->count()}). Informe o protocolo ou confira o horário/fuso do pedido."],
            default => ['nat_sem_sessao', 'A sessão de NAT foi encontrada, mas o endereço privado não tinha sessão PPPoE registrada no instante (servidor, VPN ou sessão anterior à coleta).'],
        };

        return $this->fechar($resultado, $dias, $pastas);
    }

    /** Eventos de NAT do IP:porta público na janela (só os que batem exatamente). */
    private function eventosNat(string $ip, ?int $porta, Carbon $momento): array
    {
        // Com porta: a sessão pode ter aberto horas antes (TCP longo). Sem
        // porta o IP do CGNAT aparece milhares de vezes por minuto, então só
        // os 10 min em volta.
        $alvo = $porta === null ? $ip : "$ip:$porta";
        $linhas = $this->vl->consultar(
            'app_name:' . VictoriaLogs::frase(self::NAT_APP) . ' ' . VictoriaLogs::frase($alvo),
            $porta === null ? $momento->copy()->subMinutes(10) : $momento->copy()->subHours(config('nflog.janela_nat_horas')),
            $porta === null ? $momento->copy()->addMinutes(10) : $momento->copy()->addHours(2),
            20000,
        );

        $eventos = [];
        foreach ($linhas as $linha) {
            $d = json_decode(trim((string) ($linha['_msg'] ?? '')), true);
            if (!is_array($d) || empty($d['pub']) || empty($d['priv'])) continue;
            [$pubIp, $pubPorta] = $this->separar($d['pub']);
            if ($pubIp !== $ip || ($porta !== null && (int) $pubPorta !== $porta)) continue;
            [$privIp] = $this->separar($d['priv']);
            $eventos[] = [
                'ev' => $d['ev'] ?? '', 'proto' => $d['proto'] ?? '', 'priv' => $d['priv'], 'priv_ip' => $privIp,
                // horário do roteador (fuso do provedor); sem ele, a recepção
                'quando' => !empty($d['t'])
                    ? Carbon::parse($d['t'], config('nflog.fuso'))->setTimezone(config('app.timezone'))
                    : Carbon::parse($linha['_time'])->setTimezone(config('app.timezone')),
            ];
        }
        return $eventos;
    }

    /** Chegou algum evento de NAT (de qualquer assinante) no minuto do instante (ou entre $momento e $ate)? */
    private function haDadosNat(Carbon $momento, ?Carbon $ate = null): bool
    {
        $r = $this->vl->consultar('app_name:' . VictoriaLogs::frase(self::NAT_APP) . ' | stats count() n',
            $ate ? $momento : $momento->copy()->subMinute(), $ate ?? $momento->copy()->addMinute(), 1);
        return (int) ($r[0]['n'] ?? 0) > 0;
    }

    /** "1.2.3.4:5" → ["1.2.3.4", "5"] (IPv6 entre colchetes ou com vários ":"). */
    private function separar(string $enderecoPorta): array
    {
        $pos = strrpos($enderecoPorta, ':');
        return $pos === false ? [$enderecoPorta, null] : [trim(substr($enderecoPorta, 0, $pos), '[]'), substr($enderecoPorta, $pos + 1)];
    }

    private function pastasNat(): array
    {
        $base = rtrim(config('nflog.logs_base'), '/') . '/arquivo';
        return array_map('basename', glob("$base/nat-*", GLOB_ONLYDIR) ?: []);
    }

    private function anotarSessao(SessaoPppoe $s, array &$dias, array &$pastas): void
    {
        $pastas[] = $s->roteador;
        if ($s->origem_inicio === 'syslog') $dias[$s->inicio->format('Y-m-d')] = true;
        if ($s->fim && $s->origem_fim === 'syslog') $dias[$s->fim->format('Y-m-d')] = true;
    }

    private function sessaoParaRelatorio(SessaoPppoe $s): array
    {
        return [
            'login' => $s->login, 'ip' => $s->ip, 'mac' => $s->mac, 'roteador' => $s->roteador,
            'inicio' => $s->inicio->toIso8601String(), 'fim' => $s->fim?->toIso8601String(),
            'origem_inicio' => $s->origem_inicio, 'origem_fim' => $s->origem_fim,
        ];
    }

    private function fechar(array $resultado, array $dias, array $pastas): array
    {
        $pastas = array_values(array_unique(array_filter($pastas)));
        foreach (array_keys($dias) as $dia) {
            $resultado['integridade'][] = $this->integridade->verificarDia($dia, $pastas);
        }
        usort($resultado['integridade'], fn ($a, $b) => strcmp($a['dia'], $b['dia']));
        return $resultado;
    }
}
