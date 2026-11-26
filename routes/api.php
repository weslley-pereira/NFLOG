<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\LicencaController;
use App\Http\Controllers\PainelController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/login/codigo', [AuthController::class, 'loginCodigo'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/conta/2fa/novo', [ContaController::class, 'novoSegredo']);
    Route::post('/conta/2fa/ativar', [ContaController::class, 'ativar'])->middleware('throttle:10,1');
    Route::post('/conta/2fa/desativar', [ContaController::class, 'desativar'])->middleware('throttle:5,1');
    Route::put('/conta/senha', [ContaController::class, 'senha'])->middleware('throttle:5,1');
    Route::put('/conta/email', [ContaController::class, 'email'])->middleware('throttle:5,1');

    Route::get('/painel/saude', [PainelController::class, 'saude']);

    Route::get('/consultas', [ConsultaController::class, 'index']);
    Route::post('/consultas/buscar', [ConsultaController::class, 'buscar'])->middleware('throttle:30,1');
    Route::post('/consultas', [ConsultaController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/consultas/{consulta}', [ConsultaController::class, 'show']);
    Route::get('/consultas/{consulta}/pdf', [ConsultaController::class, 'pdf']);

    Route::get('/configuracoes', [ConfiguracaoController::class, 'index']);
    Route::put('/configuracoes/empresa', [ConfiguracaoController::class, 'salvarEmpresa']);
    Route::put('/configuracoes/roteadores', [ConfiguracaoController::class, 'salvarRoteadores']);
    Route::put('/configuracoes/gestao', [ConfiguracaoController::class, 'salvarGestao']);
    Route::post('/configuracoes/gestao/testar', [ConfiguracaoController::class, 'testarGestao'])->middleware('throttle:10,1');
    Route::get('/licenca', [LicencaController::class, 'mostrar']);
    Route::put('/licenca', [LicencaController::class, 'salvar'])->middleware('throttle:10,1');
    Route::post('/licenca/verificar', [LicencaController::class, 'verificar'])->middleware('throttle:10,1');
    Route::get('/configuracoes/script-mikrotik', [ConfiguracaoController::class, 'scriptMikrotik']);
});
