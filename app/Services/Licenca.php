<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Support\NfLicencasAssinatura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Licença do NF Log pelo nf-licencas (licenca.nortefibra.com.br, mesmo
 * servidor do NF-DNS e do NFC; chave NFLOG-XXXXX-...). As respostas vêm
 * assinadas (Ed25519) e são conferidas com a chave pública: servidor falso
 * ou DNS sequestrado não aprovam licença.
 *
 * Sem licença válida, só NOVAS consultas são bloqueadas: a coleta continua
 * (o provedor é obrigado a guardar os registros -- Marco Civil, art. 13) e
 * os relatórios já emitidos seguem disponíveis. Central fora do ar: vale a
 * última verificação boa por TOLERANCIA_DIAS.
 */
class Licenca
{
    public const TOLERANCIA_DIAS = 15;

    public function chave(): ?string
    {
        return Configuracao::obter('licenca_chave') ?: null;
    }

    public function definirChave(?string $chave): void
    {
        Configuracao::definir('licenca_chave', $chave ? strtoupper(trim($chave)) : '');
        Configuracao::definirJson('licenca_estado', []);
    }

    /** Identificador desta instalação (64 hex): o nf-licencas limita quantos servidores usam a chave. */
    public function instancia(): string
    {
        $instancia = Configuracao::obter('licenca_instancia');
        if (!$instancia || !preg_match('/^[a-f0-9]{64}$/', $instancia)) {
            $instancia = bin2hex(random_bytes(32));
            Configuracao::definir('licenca_instancia', $instancia);
        }
        return $instancia;
    }

    /** @return array{situacao: string, mensagem: string, status: ?string, cliente: ?string, valido_ate: ?string, verificado_em: ?string, ok_em: ?string} */
    public function estado(): array
    {
        $e = Configuracao::obterJson('licenca_estado');
        $base = ['status' => $e['status'] ?? null, 'cliente' => $e['cliente'] ?? null, 'valido_ate' => $e['valido_ate'] ?? null,
            'verificado_em' => $e['verificado_em'] ?? null, 'ok_em' => $e['ok_em'] ?? null, 'erro' => $e['erro'] ?? null];

        if (!$this->chave()) {
            return ['situacao' => 'sem_licenca', 'mensagem' => 'Nenhuma licença informada. A coleta continua; novas consultas ficam bloqueadas até cadastrar a chave.'] + $base;
        }
        $okEm = $base['ok_em'] ? Carbon::parse($base['ok_em']) : null;
        $dentroDaFolga = $okEm && $okEm->gt(now()->subDays(self::TOLERANCIA_DIAS));
        $naoVenceu = !$base['valido_ate'] || $base['valido_ate'] >= now()->toDateString();

        if ($base['status'] === 'ativa' && $dentroDaFolga && $naoVenceu) {
            return ['situacao' => 'valida', 'mensagem' => 'Licença válida' . ($base['cliente'] ? " ({$base['cliente']})" : '') . '.'] + $base;
        }
        if ($base['status'] === 'ativa' && !$dentroDaFolga) {
            return ['situacao' => 'invalida', 'mensagem' => 'Sem confirmar a licença com a central há mais de ' . self::TOLERANCIA_DIAS . ' dias. Confira a conexão deste servidor com licenca.nortefibra.com.br.'] + $base;
        }
        $motivos = [
            'vencida' => 'Licença vencida.', 'suspensa' => 'Licença suspensa.', 'revogada' => 'Licença revogada.',
            'outra_instancia' => 'Esta chave já está em uso em outro servidor.', 'inexistente' => 'Chave não encontrada na central.',
        ];
        return ['situacao' => 'invalida', 'mensagem' => ($motivos[$base['status']] ?? 'Licença ainda não verificada.') . ' Novas consultas ficam bloqueadas; a coleta continua.'] + $base;
    }

    public function permiteConsultas(): bool
    {
        return $this->estado()['situacao'] === 'valida';
    }

    /** Pergunta à central e guarda o resultado. Falha de rede não apaga o último estado bom. */
    public function verificar(): array
    {
        $chave = $this->chave();
        if (!$chave) return $this->estado();
        $config = config('nflog.licencas');
        $nonce = bin2hex(random_bytes(12));
        $anterior = Configuracao::obterJson('licenca_estado');

        try {
            $resposta = Http::timeout(15)->acceptJson()->post(rtrim($config['url'], '/') . '/api/v1/verificar', [
                'chave' => $chave, 'instancia' => $this->instancia(), 'nonce' => $nonce,
                'hostname' => substr(preg_replace('/[^A-Za-z0-9.+_-]/', '', gethostname() ?: ''), 0, 255),
                'versao' => 'nflog-' . config('nflog.versao'),
            ]);
            if (!$resposta->successful()) throw new RuntimeException('a central respondeu HTTP ' . $resposta->status());
            $dados = NfLicencasAssinatura::conferir($resposta->json() ?? [], $config['chave_publica']);
            if ($dados === null) throw new RuntimeException('assinatura da resposta não confere');
            if (($dados['nonce'] ?? null) !== $nonce || ($dados['chave_final'] ?? null) !== substr($chave, -5)) {
                throw new RuntimeException('resposta não corresponde a esta verificação');
            }
        } catch (\Throwable $e) {
            Configuracao::definirJson('licenca_estado', array_merge($anterior, ['verificado_em' => now()->toIso8601String(), 'erro' => $e->getMessage()]));
            return $this->estado();
        }

        Configuracao::definirJson('licenca_estado', [
            'status' => $dados['status'], 'cliente' => $dados['cliente'] ?? null, 'valido_ate' => $dados['valido_ate'] ?? null,
            'verificado_em' => now()->toIso8601String(), 'erro' => null,
            'ok_em' => $dados['status'] === 'ativa' ? now()->toIso8601String() : ($anterior['ok_em'] ?? null),
        ]);
        return $this->estado();
    }

    /** Sinal de vida (5 min): a central mostra online/offline e avisa no Telegram. */
    public function sinal(array $problemas, bool $operando): void
    {
        $chave = $this->chave();
        if (!$chave) return;
        try {
            Http::timeout(10)->acceptJson()->post(rtrim(config('nflog.licencas.url'), '/') . '/api/v1/sinal', [
                'chave' => $chave, 'instancia' => $this->instancia(),
                'estado' => ['dns_ok' => $operando, 'avisos' => count($problemas), 'erros' => 0,
                    'versao' => config('nflog.versao'), 'titulos' => array_slice($problemas, 0, 10)],
            ]);
        } catch (\Throwable) {
            // Sem internet agora: o próximo sinal tenta de novo.
        }
    }
}
