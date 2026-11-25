<?php

namespace App\Http\Controllers;

use App\Services\Licenca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicencaController extends Controller
{
    public function __construct(private readonly Licenca $licenca) {}

    public function mostrar(): JsonResponse
    {
        $chave = $this->licenca->chave();
        return response()->json($this->licenca->estado() + [
            // Só o começo e o fim da chave na tela.
            'chave' => $chave ? substr($chave, 0, 11) . '…' . substr($chave, -5) : null,
            'tolerancia_dias' => Licenca::TOLERANCIA_DIAS,
        ]);
    }

    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate(['chave' => ['required', 'string', 'regex:/^\s*NFLOG(-[A-Za-z0-9]{5}){4}\s*$/']], [
            'chave.regex' => 'A chave tem o formato NFLOG-XXXXX-XXXXX-XXXXX-XXXXX.',
        ]);
        $this->licenca->definirChave($dados['chave']);
        $this->licenca->verificar();
        return $this->mostrar();
    }

    public function verificar(): JsonResponse
    {
        $this->licenca->verificar();
        return $this->mostrar();
    }
}
