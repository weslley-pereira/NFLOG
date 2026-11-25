<?php

namespace App\Services\Conectores;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** NFC ERP: Integrações > NF Log gera o token (ability "nflog"). */
class Nfc implements Conector
{
    /**
     * @param ?string $ip IP interno do NFC (opcional): conecta nele mantendo o
     *   nome do endereço (TLS confere normalmente). Para quando o servidor não
     *   alcança o próprio domínio público (roteador sem hairpin NAT).
     */
    public function __construct(private readonly string $url, private readonly string $token, private readonly ?string $ip = null) {}

    public function nome(): string
    {
        return 'NFC ERP (' . (parse_url($this->url, PHP_URL_HOST) ?: $this->url) . ')';
    }

    public function buscar(string $login, Carbon $instante): array
    {
        try {
            $requisicao = Http::timeout(15)->acceptJson()->withToken($this->token);
            if ($this->ip) {
                $host = parse_url($this->url, PHP_URL_HOST);
                $porta = parse_url($this->url, PHP_URL_PORT) ?: (parse_url($this->url, PHP_URL_SCHEME) === 'http' ? 80 : 443);
                $requisicao = $requisicao->withOptions(['curl' => [CURLOPT_RESOLVE => ["$host:$porta:{$this->ip}"]]]);
            }
            $resposta = $requisicao
                ->get(rtrim($this->url, '/') . '/api/integracao-nflog/assinante', [
                    'login' => $login,
                    'instante' => $instante->toIso8601String(),
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('NFC não respondeu: ' . $e->getMessage());
        }

        if (in_array($resposta->status(), [401, 403], true)) {
            throw new RuntimeException('NFC recusou o acesso: token inválido ou revogado (gere outro em Integrações > NF Log).');
        }
        if (!$resposta->successful() || !is_array($resposta->json('assinantes'))) {
            throw new RuntimeException('NFC respondeu HTTP ' . $resposta->status() . '.');
        }
        return $resposta->json('assinantes');
    }
}
