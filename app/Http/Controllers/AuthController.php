<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Primeira etapa. Com a verificação em duas etapas ativa, a senha certa
     * NÃO abre a sessão: guarda o usuário como pendente (5 min) e pede o código.
     */
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $credenciais = ['email' => strtolower($dados['email']), 'password' => $dados['password']];
        if (!Auth::validate($credenciais)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        $usuario = User::where('email', $credenciais['email'])->first();

        if ($usuario->totp_ativo) {
            $request->session()->put('login_pendente', ['id' => $usuario->id, 'ate' => now()->addMinutes(5)->timestamp, 'lembrar' => $request->boolean('lembrar')]);
            return response()->json(['dois_fatores' => true]);
        }

        Auth::login($usuario, $request->boolean('lembrar'));
        $request->session()->regenerate();
        return response()->json(['usuario' => $this->dados($usuario)]);
    }

    /** Segunda etapa: código de 6 dígitos do aplicativo. */
    public function loginCodigo(Request $request): JsonResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $pendente = $request->session()->get('login_pendente');
        if (!$pendente || $pendente['ate'] < now()->timestamp) {
            $request->session()->forget('login_pendente');
            throw ValidationException::withMessages(['codigo' => 'Tempo esgotado. Entre com e-mail e senha de novo.']);
        }
        $usuario = User::find($pendente['id']);
        $passo = $usuario?->totp_ativo ? Totp::conferir($usuario->totp_segredo, $request->input('codigo'), $usuario->totp_ultimo_passo) : null;
        if ($passo === null) {
            throw ValidationException::withMessages(['codigo' => 'Código inválido.']);
        }
        $usuario->forceFill(['totp_ultimo_passo' => $passo])->save();

        $request->session()->forget('login_pendente');
        Auth::login($usuario, $pendente['lembrar']);
        $request->session()->regenerate();
        return response()->json(['usuario' => $this->dados($usuario)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->dados($request->user()));
    }

    private function dados(User $usuario): array
    {
        return $usuario->only('id', 'name', 'email') + ['dois_fatores' => (bool) $usuario->totp_ativo];
    }
}
