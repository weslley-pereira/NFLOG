<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\SessaoPppoe;
use Illuminate\Support\Facades\Crypt;

/**
 * Foto das sessões PPPoE ativas pela API do Mikrotik (/ppp/active: login,
 * IP, MAC e uptime). Cobre quem já estava conectado antes da instalação,
 * que nunca terá um "logged in" no syslog, e corrige sessão que o syslog
 * perdeu (log descartado com o roteador sob carga, coletor fora do ar).
 *
 * Roteadores em configuracoes.roteadores: [{ip, porta, usuario, senha (cifrada)}].
 */
class FotoSessoesMikrotik
{
    /** @return array<string, string> resultado por roteador */
    public function executar(): array
    {
        $resultado = [];
        foreach (Configuracao::obterJson('roteadores') as $r) {
            if (empty($r['ip']) || empty($r['usuario'])) continue;
            if (empty($r['senha'])) {
                $resultado[$r['ip']] = 'sem senha da API cadastrada (Configurações > Roteadores)';
                continue;
            }
            $api = new MikrotikApi();
            try {
                if (!$api->conectar($r['ip'], (int) ($r['porta'] ?? 8728), $r['usuario'], Crypt::decryptString($r['senha'] ?? ''), 10)) {
                    $resultado[$r['ip']] = 'falha ao conectar na API (porta ' . ($r['porta'] ?? 8728) . '): confira o serviço api do Mikrotik, o usuário, a senha e o address do usuário';
                    continue;
                }
                $ativas = $api->consultar(['/ppp/active/print', '=.proplist=name,address,caller-id,uptime']) ?? [];
                $resultado[$r['ip']] = $this->aplicar($r['ip'], $ativas) . ' sessões novas de ' . count($ativas) . ' ativas';
            } catch (\Throwable $e) {
                $resultado[$r['ip']] = 'erro: ' . $e->getMessage();
            } finally {
                $api->desconectar();
            }
        }
        return $resultado;
    }

    private function aplicar(string $roteador, array $ativas): int
    {
        $novas = 0;
        $agora = now();
        foreach ($ativas as $s) {
            if (empty($s['name']) || empty($s['address'])) continue;
            $aberta = SessaoPppoe::where('roteador', $roteador)->where('login', $s['name'])->whereNull('fim')->latest('inicio')->first();
            if ($aberta && $aberta->ip === $s['address']) continue;   // syslog já tem

            $inicio = $agora->copy()->subSeconds(self::segundos($s['uptime'] ?? '0s'));
            if ($aberta) $aberta->update(['fim' => $inicio, 'origem_fim' => 'sucessora']);
            SessaoPppoe::create([
                'roteador' => $roteador, 'login' => $s['name'], 'ip' => $s['address'],
                'mac' => isset($s['caller-id']) ? strtoupper($s['caller-id']) : null,
                'inicio' => $inicio, 'origem_inicio' => 'api',
            ]);
            $novas++;
        }
        return $novas;
    }

    /** "1w2d03:04:05" / "3h2m1s" (formatos do RouterOS) em segundos. */
    public static function segundos(string $uptime): int
    {
        $total = 0;
        if (preg_match('/(\d+):(\d+):(\d+)$/', $uptime, $m)) {
            $total += $m[1] * 3600 + $m[2] * 60 + $m[3];
            $uptime = substr($uptime, 0, -strlen($m[0]));
        }
        foreach (['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1] as $u => $s) {
            if (preg_match('/(\d+)' . $u . '/', $uptime, $m)) $total += $m[1] * $s;
        }
        return $total;
    }
}
