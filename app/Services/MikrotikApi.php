<?php

namespace App\Services;

use RuntimeException;

/**
 * Cliente da API do RouterOS (protocolo binário, porta 8728). Mesma
 * implementação do NFC ERP: toda leitura de byte checa queda/timeout -- sem
 * isso uma conexão que cai no meio prende o laço de leitura para sempre.
 */
class MikrotikApi
{
    private $socket = null;

    public function conectar(string $host, int $porta, string $usuario, string $senha, int $timeout = 10): bool
    {
        $this->socket = @fsockopen($host, $porta, $erro, $mensagem, $timeout);
        if (!$this->socket) return false;
        stream_set_timeout($this->socket, $timeout);

        try {
            $resposta = $this->executar(['/login', '=name=' . $usuario, '=password=' . $senha]);
        } catch (RuntimeException) {
            return false;
        }
        return ($resposta[0] ?? null) === '!done';
    }

    /** @return array<int, array<string, string>>|null null se o roteador devolver erro */
    public function consultar(array $palavras): ?array
    {
        $resposta = $this->executar($palavras);
        if (in_array('!trap', $resposta, true)) return null;

        $registros = [];
        $atual = null;
        foreach ($resposta as $linha) {
            if ($linha === '!re') {
                if ($atual !== null) $registros[] = $atual;
                $atual = [];
            } elseif ($atual !== null && str_starts_with($linha, '=')) {
                [$campo, $valor] = array_pad(explode('=', substr($linha, 1), 2), 2, '');
                $atual[$campo] = $valor;
            }
        }
        if ($atual !== null) $registros[] = $atual;
        return $registros;
    }

    public function executar(array $palavras): array
    {
        foreach ($palavras as $palavra) {
            fwrite($this->socket, $this->tamanho(strlen($palavra)) . $palavra);
        }
        fwrite($this->socket, chr(0));

        $resposta = [];
        while (true) {
            $tam = $this->lerTamanho();
            if ($tam === 0) {
                if (end($resposta) === '!done') break;
                continue;
            }
            $linha = '';
            while (strlen($linha) < $tam) {
                $pedaco = fread($this->socket, $tam - strlen($linha));
                if ($pedaco === false || $pedaco === '') throw new RuntimeException('Conexão com a API do Mikrotik caiu ou deu timeout.');
                $linha .= $pedaco;
            }
            $resposta[] = $linha;
        }
        return $resposta;
    }

    public function desconectar(): void
    {
        if ($this->socket) fclose($this->socket);
        $this->socket = null;
    }

    private function tamanho(int $n): string
    {
        if ($n < 0x80) return chr($n);
        if ($n < 0x4000) return chr(($n >> 8) | 0x80) . chr($n & 0xFF);
        if ($n < 0x200000) return chr(($n >> 16) | 0xC0) . chr(($n >> 8) & 0xFF) . chr($n & 0xFF);
        return chr(($n >> 24) | 0xE0) . chr(($n >> 16) & 0xFF) . chr(($n >> 8) & 0xFF) . chr($n & 0xFF);
    }

    private function lerByte(): int
    {
        $b = fread($this->socket, 1);
        if ($b === false || $b === '') throw new RuntimeException('Conexão com a API do Mikrotik caiu ou deu timeout.');
        return ord($b);
    }

    private function lerTamanho(): int
    {
        $b = $this->lerByte();
        if (($b & 0x80) === 0x00) return $b;
        if (($b & 0xC0) === 0x80) return (($b & ~0xC0) << 8) + $this->lerByte();
        if (($b & 0xE0) === 0xC0) return (($b & ~0xE0) << 16) + ($this->lerByte() << 8) + $this->lerByte();
        if (($b & 0xF0) === 0xE0) return (($b & ~0xF0) << 24) + ($this->lerByte() << 16) + ($this->lerByte() << 8) + $this->lerByte();
        return 0;
    }
}
