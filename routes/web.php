<?php

use Illuminate\Support\Facades\Route;

// SPA (Vue): toda rota que não é API cai no mesmo HTML.
Route::get('/{qualquer?}', fn () => view('app'))->where('qualquer', '^(?!api|sanctum|storage|up).*$');
