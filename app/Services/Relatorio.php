<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Consulta;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $numero = sprintf('NFLOG-%s-%06d', $consulta->created_at->format('Y'), $consulta->id);
        $pdf = Pdf::loadView('relatorios.consulta', [
            'numero' => $numero,
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
}
