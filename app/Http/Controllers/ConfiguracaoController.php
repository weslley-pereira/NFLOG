<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ConfiguracaoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'empresa' => Configuracao::empresa(),
            // A senha da API nunca volta pra tela.
            'roteadores' => array_map(fn ($r) => array_diff_key($r, ['senha' => 1]) + ['tem_senha' => !empty($r['senha'])], Configuracao::obterJson('roteadores')),
            'ip_coletor' => Configuracao::obter('ip_coletor', ''),
        ]);
    }

    public function salvarEmpresa(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'razao_social' => ['required', 'string', 'max:200'],
            'cnpj' => ['required', 'string', 'max:20'],
            'outorga' => ['nullable', 'string', 'max:200'],
            'endereco' => ['nullable', 'string', 'max:300'],
            'responsavel' => ['nullable', 'string', 'max:150'],
            'cargo_responsavel' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:40'],
        ]);
        Configuracao::definirJson('empresa', array_map(fn ($v) => $v ?? '', $dados));
        return response()->json(['mensagem' => 'Dados da empresa salvos.']);
    }

    /** Roteadores para a foto das sessões ativas pela API (opcional). */
    public function salvarRoteadores(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ip_coletor' => ['nullable', 'ip'],
            'roteadores' => ['array', 'max:20'],
            'roteadores.*.ip' => ['required', 'ip'],
            'roteadores.*.nome' => ['nullable', 'string', 'max:60'],
            'roteadores.*.interface_wan' => ['nullable', 'string', 'max:60'],
            'roteadores.*.porta' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'roteadores.*.usuario' => ['nullable', 'string', 'max:60'],
            'roteadores.*.senha' => ['nullable', 'string', 'max:200'],
        ]);
        $atuais = collect(Configuracao::obterJson('roteadores'))->keyBy('ip');
        $novos = array_map(function ($r) use ($atuais) {
            // Senha em branco = manter a que já estava.
            $r['senha'] = !empty($r['senha']) ? Crypt::encryptString($r['senha']) : ($atuais[$r['ip']]['senha'] ?? null);
            return $r;
        }, $dados['roteadores'] ?? []);
        Configuracao::definirJson('roteadores', $novos);
        Configuracao::definir('ip_coletor', $dados['ip_coletor'] ?? '');
        return response()->json(['mensagem' => 'Roteadores salvos.']);
    }

    /**
     * Script .rsc que liga, no Mikrotik, o envio de syslog e do log de NAT
     * (IPFIX com eventos de NAT, sem os campos de destino) para este coletor.
     */
    public function scriptMikrotik(Request $request)
    {
        $dados = $request->validate([
            'roteador' => ['required', 'ip'],
            'interface_wan' => ['required', 'string', 'max:60', 'regex:/^[^"\\\\\r\n]+$/'],
        ]);
        $coletor = Configuracao::obter('ip_coletor');
        abort_unless($coletor, 422, 'Informe o IP deste coletor em Configurações.');

        $semDestino = 'dst-address=no dst-port=no nat-dst-address=no nat-dst-port=no dst-mac-address=no src-mac-address=no '
            . 'src-address-mask=no dst-address-mask=no gateway=no ttl=no ip-header-length=no is-multicast=no ip-total-length=no '
            . 'ipv6-flow-label=no udp-length=no tcp-seq-num=no tcp-ack-num=no tcp-window-size=no igmp-type=no icmp-type=no '
            . 'icmp-code=no tos=no tcp-flags=no in-interface=no out-interface=no packets=no bytes=no';
        $linhas = [
            '# NF Log -- envio de syslog e do log de NAT para o coletor ' . $coletor,
            '# Gerado em ' . now()->format('d/m/Y H:i') . '. Pode importar de novo: só cria o que falta.',
            '/system logging action',
            ':if ([:len [find name="nflog"]] = 0) do={ add name=nflog target=remote remote=' . $coletor . ' remote-port=514 src-address=' . $dados['roteador'] . ' }',
            '/system logging',
        ];
        foreach (['info', 'warning', 'error', 'critical'] as $t) {
            $linhas[] = ':if ([:len [find action=nflog topics=' . $t . ']] = 0) do={ add action=nflog topics=' . $t . ' }';
        }
        $linhas = array_merge($linhas, [
            '# Log de NAT: só eventos de sessão de NAT com origem privada e pública -- sem destino (art. 14 do Marco Civil).',
            '/ip traffic-flow ipfix set nat-events=yes ' . $semDestino,
            '/ip traffic-flow target',
            ':if ([:len [find dst-address=' . $coletor . ' port=2055]] = 0) do={ add dst-address=' . $coletor . ' port=2055 version=ipfix src-address=' . $dados['roteador'] . ' }',
            '/ip traffic-flow set enabled=yes interfaces="' . $dados['interface_wan'] . '"',
        ]);

        return response(implode("\n", $linhas) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nflog-mikrotik.rsc"',
        ]);
    }
}
