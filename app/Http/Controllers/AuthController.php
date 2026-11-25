<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt(['email' => strtolower($dados['email']), 'password' => $dados['password']], $request->boolean('lembrar'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        $request->session()->regenerate();
        return response()->json(['usuario' => Auth::user()->only('id', 'name', 'email')]);
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
        return response()->json($request->user()->only('id', 'name', 'email'));
    }
}
