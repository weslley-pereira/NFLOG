<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consulta extends Model
{
    protected $fillable = [
        'autoridade', 'documento', 'processo', 'recebido_em', 'ip', 'porta', 'protocolo',
        'momento', 'momento_informado', 'fuso_informado', 'resultado', 'conclusao',
        'relatorio_arquivo', 'relatorio_sha256', 'user_id',
    ];

    protected $casts = [
        'recebido_em' => 'date',
        'momento' => 'datetime',
        'resultado' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
