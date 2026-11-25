<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Verificação em duas etapas por aplicativo (RFC 6238: SHA-1, 6 dígitos,
 * passo de 30 s) -- Google Authenticator, Microsoft Authenticator, Authy etc.
 */
class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function novoSegredo(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(20)) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        return implode('', array_map(fn ($b) => self::ALFABETO[bindec($b)], str_split($bits, 5)));
    }

    public static function codigo(string $segredo, int $passo): string
    {
        $hash = hash_hmac('sha1', pack('J', $passo), self::decodificar($segredo), true);
        $o = ord($hash[19]) & 0x0F;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Passo aceito (tolerância de ±1 para o relógio do celular) ou null. Passo
     * <= $ultimoPasso é recusado: o mesmo código não entra duas vezes.
     */
    public static function conferir(string $segredo, string $codigo, ?int $ultimoPasso): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== 6) return null;
        $agora = intdiv(time(), 30);
        foreach ([0, -1, 1] as $desvio) {
            $passo = $agora + $desvio;
            if (($ultimoPasso === null || $passo > $ultimoPasso) && hash_equals(self::codigo($segredo, $passo), $codigo)) {
                return $passo;
            }
        }
        return null;
    }

    public static function uri(string $conta, string $segredo, string $emissor): string
    {
        return 'otpauth://totp/' . rawurlencode("$emissor:$conta") . "?secret=$segredo&issuer=" . rawurlencode($emissor);
    }

    /** QR em SVG (data URI) para mostrar na tela. */
    public static function qr(string $texto): string
    {
        return (new QRCode(new QROptions(['outputType' => 'svg', 'outputBase64' => true, 'addQuietzone' => true])))->render($texto);
    }

    private static function decodificar(string $texto): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto))) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        }
        $bin = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $bin .= chr(bindec($byte));
        return $bin;
    }
}
