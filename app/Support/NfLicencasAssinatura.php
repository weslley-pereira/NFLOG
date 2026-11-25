<?php

namespace App\Support;

/**
 * Confere o envelope assinado que o nf-licencas devolve ({dados, assinatura},
 * Ed25519, base64 URL-safe). Mesma lógica do NFC ERP.
 */
class NfLicencasAssinatura
{
    /** Dados decodificados, ou null se a assinatura não bater com a chave pública. */
    public static function conferir(array $envelope, string $chavePublicaBase64): ?array
    {
        $dadosB64 = (string) ($envelope['dados'] ?? '');
        $assinaturaB64 = (string) ($envelope['assinatura'] ?? '');
        if ($dadosB64 === '' || $assinaturaB64 === '') {
            return null;
        }

        $destrocarUrlSafe = fn (string $s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);

        $json = $destrocarUrlSafe($dadosB64);
        $assinatura = $destrocarUrlSafe($assinaturaB64);
        $chavePublica = base64_decode($chavePublicaBase64, true);

        if ($json === false || $assinatura === false || $chavePublica === false
            || strlen($assinatura) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($chavePublica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        if (!sodium_crypto_sign_verify_detached($assinatura, $json, $chavePublica)) {
            return null;
        }

        $dados = json_decode($json, true);
        return is_array($dados) ? $dados : null;
    }
}
