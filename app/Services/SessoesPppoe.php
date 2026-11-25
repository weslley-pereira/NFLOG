<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\SessaoPppoe;
use Illuminate\Support\Carbon;

/**
 * Monta as sessões PPPoE (quem esteve com cada IP privado, de quando a
 * quando) a partir do syslog do Mikrotik, que registra com os tópicos
 * "pppoe,ppp,info,account":
 *
 *   <login> logged in, <IP> from <MAC>
 *   <login> logged out, <duração s> <bytes in> <bytes out> <pct in> <pct out> from <MAC>
 *
 * Casos que o syslog sozinho não fecha:
 * - quem já estava conectado antes da instalação não tem "logged in" (é
 *   o único evento com o IP): entra pela foto das sessões ativas via API
 *   (FotoSessoesMikrotik, origem_inicio = api, início = agora - uptime);
 * - login novo do mesmo login sem logout antes (roteador reiniciou, log
 *   perdido): a sessão anterior fecha no início da nova (origem_fim = sucessora).
 */
class SessoesPppoe
{
    public const TOPICOS = 'pppoe,ppp,info,account';
    private const CURSOR = 'sessoes_pppoe_cursor';

    public function __construct(private readonly VictoriaLogs $vl) {}

    /** @return array{eventos:int, abertas:int, fechadas:int} */
    public function importar(): array
    {
        $cursor = Configuracao::obter(self::CURSOR);
        // Relê os últimos 5 min a cada rodada: a fila do rsyslog pode entregar
        // um evento com atraso. aplicar() ignora o que já foi gravado.
        $de = $cursor ? Carbon::parse($cursor)->subMinutes(5) : now()->subDays(2);
        $ate = now();
        $resumo = ['eventos' => 0, 'abertas' => 0, 'fechadas' => 0];

        do {
            $linhas = $this->vl->consultar('app_name:' . VictoriaLogs::frase(self::TOPICOS) . ' | sort by (_time)', $de, $ate, 5000);
            foreach ($linhas as $linha) {
                $quando = Carbon::parse($linha['_time'])->setTimezone(config('app.timezone'));
                $this->aplicar($this->roteador($linha), trim((string) ($linha['_msg'] ?? '')), $quando, $resumo);
                $resumo['eventos']++;
                $ultimo = $linha['_time'];
            }
            if (isset($ultimo)) {
                Configuracao::definir(self::CURSOR, $ultimo);
                $de = Carbon::parse($ultimo);
            }
        } while (count($linhas) === 5000 && $resumo['eventos'] < 1000000);

        return $resumo;
    }

    private function aplicar(string $roteador, string $msg, Carbon $quando, array &$resumo): void
    {
        if (preg_match('/^(\S+) logged in, ([0-9a-f.:]+) from ([0-9A-F:]{17})/i', $msg, $m)) {
            [, $login, $ip, $mac] = $m;
            if (SessaoPppoe::where('roteador', $roteador)->where('login', $login)->where('inicio', $quando)->exists()) {
                return; // já importado (releitura da janela)
            }
            SessaoPppoe::where('roteador', $roteador)->where('login', $login)->whereNull('fim')->where('inicio', '<=', $quando)
                ->update(['fim' => $quando, 'origem_fim' => 'sucessora']);
            SessaoPppoe::create([
                'roteador' => $roteador, 'login' => $login, 'ip' => $ip, 'mac' => strtoupper($mac),
                'inicio' => $quando, 'origem_inicio' => 'syslog',
            ]);
            $resumo['abertas']++;
            return;
        }

        if (preg_match('/^(\S+) logged out, (\d+)\b.*from ([0-9A-F:]{17})/i', $msg, $m)) {
            // Sem sessão aberta (conectou antes da instalação e a foto da API
            // não pegou): não há IP pra registrar, nada a fazer.
            $fechou = SessaoPppoe::where('roteador', $roteador)->where('login', $m[1])->whereNull('fim')
                ->update(['fim' => $quando, 'origem_fim' => 'syslog']);
            $resumo['fechadas'] += $fechou;
        }
    }

    /** IP do roteador que mandou a linha (campo do coletor ou hostname). */
    private function roteador(array $linha): string
    {
        foreach ($linha as $campo => $valor) {
            if (str_ends_with($campo, 'origem_ip')) return (string) $valor;
        }
        return (string) ($linha['hostname'] ?? '');
    }

    /** Sessão que estava com o IP privado no instante (com folga). */
    public static function noInstante(string $ip, Carbon $instante, int $folga = 0): ?SessaoPppoe
    {
        return SessaoPppoe::where('ip', $ip)
            ->where('inicio', '<=', $instante->copy()->addSeconds($folga))
            ->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>=', $instante->copy()->subSeconds($folga)))
            ->latest('inicio')->first();
    }
}
