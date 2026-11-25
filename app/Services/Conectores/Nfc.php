<?php

namespace App\Services\Conectores;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** NFC ERP: Integrações > NF Log gera o token (ability "nflog"). */
class Nfc implements Conector
{
    public function __construct(private readonly string $url, private readonly string $token) {}

    public function nome(): string
    {
        return 'NFC ERP (' . (parse_url($this->url, PHP_URL_HOST) ?: $this->url) . ')';
    }

    public function buscar(string $login, Carbon $instante): array
    {
        try {
            $resposta = Http::timeout(15)->acceptJson()->withToken($this->token)
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
