<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Minha conta: senha e verificação em duas etapas. */
class ContaController extends Controller
{
    /** Segredo novo (ainda não vale): fica na sessão até o código ser confirmado. */
    public function novoSegredo(Request $request): JsonResponse
    {
        $segredo = Totp::novoSegredo();
        $request->session()->put('totp_novo', $segredo);
        $emissor = 'NF Log' . (($empresa = Configuracao::empresa()['razao_social'] ?? '') ? " - $empresa" : '');
        $uri = Totp::uri($request->user()->email, $segredo, mb_substr($emissor, 0, 60));
        return response()->json(['segredo' => $segredo, 'qr' => Totp::qr($uri)]);
    }

    public function ativar(Request $request): JsonResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $segredo = $request->session()->get('totp_novo');
        $passo = $segredo ? Totp::conferir($segredo, $request->input('codigo'), null) : null;
        if ($passo === null) {
            throw ValidationException::withMessages(['codigo' => 'Código inválido. Confira o horário do celular e tente de novo.']);
        }
        $request->user()->forceFill(['totp_segredo' => $segredo, 'totp_ativo' => true, 'totp_ultimo_passo' => $passo])->save();
        $request->session()->forget('totp_novo');
        return response()->json(['mensagem' => 'Verificação em duas etapas ativada.']);
    }

    public function desativar(Request $request): JsonResponse
    {
        $request->validate(['senha' => ['required', 'string']]);
        if (!Hash::check($request->input('senha'), $request->user()->password)) {
            throw ValidationException::withMessages(['senha' => 'Senha incorreta.']);
        }
        $request->user()->forceFill(['totp_segredo' => null, 'totp_ativo' => false, 'totp_ultimo_passo' => null])->save();
        return response()->json(['mensagem' => 'Verificação em duas etapas desativada.']);
    }

    /** Troca o e-mail de login (confirma com a senha atual). */
    public function email(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $request->user()->id],
            'senha' => ['required', 'string'],
        ], ['email.unique' => 'Este e-mail já é de outro usuário.']);
        if (!Hash::check($dados['senha'], $request->user()->password)) {
            throw ValidationException::withMessages(['senha' => 'Senha incorreta.']);
        }
        $request->user()->forceFill(['email' => strtolower(trim($dados['email']))])->save();
        return response()->json(['mensagem' => 'E-mail alterado. Use o novo e-mail no próximo login.']);
    }

    public function senha(Request $request): JsonResponse
    {
        $request->validate([
            'atual' => ['required', 'string'],
            'nova' => ['required', 'string', 'confirmed', Password::min(10)],
        ]);
        if (!Hash::check($request->input('atual'), $request->user()->password)) {
            throw ValidationException::withMessages(['atual' => 'Senha atual incorreta.']);
        }
        $request->user()->forceFill(['password' => $request->input('nova')])->save();
        return response()->json(['mensagem' => 'Senha alterada.']);
    }
}
