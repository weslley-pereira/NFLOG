<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\PainelController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/painel/saude', [PainelController::class, 'saude']);

    Route::get('/consultas', [ConsultaController::class, 'index']);
    Route::post('/consultas/buscar', [ConsultaController::class, 'buscar'])->middleware('throttle:30,1');
    Route::post('/consultas', [ConsultaController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/consultas/{consulta}', [ConsultaController::class, 'show']);
    Route::get('/consultas/{consulta}/pdf', [ConsultaController::class, 'pdf']);

    Route::get('/configuracoes', [ConfiguracaoController::class, 'index']);
    Route::put('/configuracoes/empresa', [ConfiguracaoController::class, 'salvarEmpresa']);
    Route::put('/configuracoes/roteadores', [ConfiguracaoController::class, 'salvarRoteadores']);
    Route::get('/configuracoes/script-mikrotik', [ConfiguracaoController::class, 'scriptMikrotik']);
});
