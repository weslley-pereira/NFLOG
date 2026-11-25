<?php

use Illuminate\Support\Facades\Route;

// Página pública de autenticidade do relatório (QR Code do PDF).
Route::get('/verificar/{codigo?}', [App\Http\Controllers\VerificacaoController::class, 'mostrar'])
    ->where('codigo', '[A-Za-z0-9-]{1,24}')->middleware('throttle:30,1');

// SPA (Vue): toda rota que não é API cai no mesmo HTML.
Route::get('/{qualquer?}', fn () => view('app'))->where('qualquer', '^(?!api|sanctum|storage|up|verificar).*$');
