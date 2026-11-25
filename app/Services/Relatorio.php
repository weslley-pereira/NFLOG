<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Consulta;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

/**
 * PDF da consulta para responder o ofício. O sha256 do arquivo fica
 * gravado na consulta: um PDF que circule depois pode ser conferido contra
 * esse registro (e, na versão com licença, contra a central).
 */
class Relatorio
{
    public function gerar(Consulta $consulta): Consulta
    {
        $numero = self::numero($consulta);
        if (!$consulta->codigo_verificacao) {
            $consulta->update(['codigo_verificacao' => self::novoCodigo()]);
        }
        $url = rtrim((string) config('nflog.url_verificacao'), '/') . '/verificar/' . $consulta->codigo_verificacao;
        $pdf = Pdf::loadView('relatorios.consulta', [
            'numero' => $numero,
            'verificacao' => [
                'codigo' => $consulta->codigo_verificacao,
                'url' => $url,
                'qr' => (new QRCode(new QROptions(['outputType' => 'png', 'outputBase64' => true, 'scale' => 4, 'addQuietzone' => false])))->render($url),
            ],
            'consulta' => $consulta,
            'r' => $consulta->resultado,
            'empresa' => Configuracao::empresa(),
            'fuso' => config('app.timezone'),
            'gerado_em' => now(),
            'gerado_por' => $consulta->usuario?->name,
        ])->setPaper('a4')
            // Só os glifos usados (a DejaVu inteira deixava o PDF com ~1 MB);
            // e nada buscado da internet ao montar o PDF.
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);

        $arquivo = "relatorios/$numero.pdf";
        Storage::disk('local')->put($arquivo, $pdf->output());
        $consulta->update([
            'relatorio_arquivo' => $arquivo,
            'relatorio_sha256' => hash('sha256', Storage::disk('local')->get($arquivo)),
        ]);
        return $consulta;
    }

    public static function numero(Consulta $consulta): string
    {
        return sprintf('NFLOG-%s-%06d', $consulta->created_at->format('Y'), $consulta->id);
    }

    /** XXXX-XXXX-XXXX-XXXX sem letras que se confundem (0/O, 1/I/L). */
    private static function novoCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $codigo = implode('-', array_map(fn () => implode('', array_map(fn () => $alfabeto[random_int(0, strlen($alfabeto) - 1)], range(1, 4))), range(1, 4)));
        } while (Consulta::where('codigo_verificacao', $codigo)->exists());
        return $codigo;
    }
}
