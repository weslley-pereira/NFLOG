<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Consulta;
use App\Services\Relatorio;
use Illuminate\Http\Request;

/**
 * Página PÚBLICA de autenticidade do relatório (fora do painel, que é só
 * rede interna). Mostra só o que prova a origem -- número, emissão, empresa e
 * o SHA-256 registrado --, nunca dado do assinante nem do ofício. A
 * conferência do arquivo é feita no navegador de quem verifica (o PDF não
 * é enviado).
 */
class VerificacaoController extends Controller
{
    public function mostrar(Request $request, ?string $codigo = null)
    {
        $codigo = strtoupper(trim((string) ($codigo ?? $request->query('codigo', ''))));
        $consulta = $codigo !== '' ? Consulta::where('codigo_verificacao', $codigo)->whereNotNull('relatorio_sha256')->first() : null;

        return response()->view('verificar', [
            'codigo' => $codigo,
            'consulta' => $consulta,
            'numero' => $consulta ? Relatorio::numero($consulta) : null,
            'empresa' => Configuracao::empresa(),
        ])->header('X-Robots-Tag', 'noindex');
    }
}
