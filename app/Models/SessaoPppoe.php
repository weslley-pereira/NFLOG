<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessaoPppoe extends Model
{
    protected $table = 'sessoes_pppoe';

    protected $fillable = ['roteador', 'login', 'ip', 'mac', 'inicio', 'fim', 'origem_inicio', 'origem_fim'];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
    ];
}
