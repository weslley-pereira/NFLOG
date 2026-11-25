<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurações em chave/valor (dados da empresa, roteadores, cursores).
 * Valores com `json` viram array; os sigilosos vão cifrados com a APP_KEY.
 */
class Configuracao extends Model
{
    protected $table = 'configuracoes';
    protected $primaryKey = 'chave';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['chave', 'valor'];

    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        $valor = static::find($chave)?->valor;
        return $valor === null ? $padrao : $valor;
    }

    public static function definir(string $chave, mixed $valor): void
    {
        static::updateOrCreate(['chave' => $chave], ['valor' => $valor === null ? null : (string) $valor]);
    }

    public static function obterJson(string $chave, array $padrao = []): array
    {
        $valor = json_decode((string) static::obter($chave, ''), true);
        return is_array($valor) ? $valor : $padrao;
    }

    public static function definirJson(string $chave, array $valor): void
    {
        static::definir($chave, json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Dados da empresa que saem no cabeçalho do relatório. */
    public static function empresa(): array
    {
        return static::obterJson('empresa', [
            'razao_social' => '', 'cnpj' => '', 'outorga' => '', 'endereco' => '',
            'responsavel' => '', 'cargo_responsavel' => '', 'email' => '', 'telefone' => '',
        ]);
    }
}
