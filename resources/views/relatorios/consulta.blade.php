@php
    $dt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->setTimezone($fuso)->format('d/m/Y H:i:s') : '—';
    $conclusoes = [
        'identificado' => ['Usuário identificado', 'ok'],
        'ambiguo' => ['Mais de um usuário no instante', 'alerta'],
        'sem_porta' => ['Não individualizável sem a porta lógica', 'alerta'],
        'nat_sem_sessao' => ['Endereço privado sem sessão de assinante', 'alerta'],
        'sem_registro' => ['Sem registro correspondente', 'neutro'],
        'sem_dados' => ['Sem registros de NAT no período', 'alerta'],
    ];
    [$titulo, $classe] = $conclusoes[$r['conclusao']] ?? [$r['conclusao'], 'neutro'];
    $situacoes = ['integro' => 'Íntegro', 'falha' => 'FALHA', 'aberto' => 'Dia em andamento', 'sem_manifesto' => 'Sem manifesto'];
    $arquivos = ['confere' => 'confere', 'so_na_copia_externa' => 'só na cópia externa', 'divergente' => 'DIVERGENTE', 'fora_do_manifesto' => 'fora do manifesto'];
    $sessoes = collect($r['candidatos'] ?? [])->pluck('sessao')->filter();
    if (!empty($r['ip_dedicado'])) $sessoes->push($r['ip_dedicado']);
    $sessoes = $sessoes->unique(fn ($s) => $s['login'] . '|' . $s['inicio'])->values();
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18mm 15mm 18mm 15mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2933; line-height: 1.4; }
    h1 { font-size: 15pt; margin: 0 0 2px; color: #0f2a44; }
    h2 { font-size: 11pt; color: #0f2a44; border-bottom: 1.5px solid #d5dde6; padding-bottom: 2px; margin: 14px 0 6px; }
    .sub { color: #52606d; font-size: 8.5pt; }
    .empresa { border-bottom: 2px solid #0f2a44; padding-bottom: 6px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin: 4px 0 8px; font-size: 8.5pt; }
    th, td { border: 1px solid #d5dde6; padding: 3px 5px; text-align: left; vertical-align: top; }
    th { background: #f0f4f8; width: 30%; }
    table.lista th { width: auto; }
    .box { padding: 7px 10px; margin: 6px 0 8px; border-left: 4px solid; }
    .ok { background: #ecf8f0; border-color: #2f9e5b; }
    .alerta { background: #fff6e6; border-color: #d9822b; }
    .neutro { background: #eef2f6; border-color: #7b8794; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; }
    .nota { font-size: 8pt; color: #52606d; }
    .assinatura { margin-top: 30px; width: 60%; border-top: 1px solid #52606d; padding-top: 3px; font-size: 8.5pt; }
    .rodape { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #7b8794; text-align: center; }
</style>
</head>
<body>
<div class="rodape">{{ $numero }} · gerado em {{ $gerado_em->format('d/m/Y H:i:s') }} pelo NF Log · o resumo SHA-256 deste arquivo fica registrado no sistema para conferência de autenticidade</div>

<div class="empresa">
    <strong>{{ $empresa['razao_social'] ?: 'Provedor (configurar os dados da empresa)' }}</strong>
    @if($empresa['cnpj']) · CNPJ {{ $empresa['cnpj'] }} @endif
    @if($empresa['outorga']) · {{ $empresa['outorga'] }} @endif
    <div class="sub">{{ $empresa['endereco'] }} @if($empresa['email']) · {{ $empresa['email'] }} @endif @if($empresa['telefone']) · {{ $empresa['telefone'] }} @endif</div>
</div>

<h1>Relatório de registros de conexão</h1>
<div class="sub">Nº {{ $numero }} · emitido em {{ $gerado_em->format('d/m/Y \à\s H:i') }} (horário de Brasília)</div>

<h2>1. Solicitação</h2>
<table>
    <tr><th>Autoridade solicitante</th><td>{{ $consulta->autoridade ?: '—' }}</td></tr>
    <tr><th>Documento (ofício/mandado)</th><td>{{ $consulta->documento ?: '—' }}</td></tr>
    <tr><th>Processo / procedimento</th><td>{{ $consulta->processo ?: '—' }}</td></tr>
    <tr><th>Recebido em</th><td>{{ $consulta->recebido_em?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Endereço IP consultado</th><td class="mono">{{ $r['ip'] }}</td></tr>
    <tr><th>Porta lógica de origem</th><td class="mono">{{ $r['porta'] ?? 'não informada' }}</td></tr>
    <tr><th>Protocolo</th><td>{{ $r['protocolo'] ? strtoupper($r['protocolo']) : 'não informado' }}</td></tr>
    <tr><th>Data e hora informadas</th><td>{{ $consulta->momento_informado }} ({{ $consulta->fuso_informado }})</td></tr>
    <tr><th>Data e hora consideradas</th><td>{{ $dt($r['momento']) }} (horário de Brasília), tolerância de ±{{ $r['tolerancia_segundos'] }} s</td></tr>
</table>

<h2>2. Resultado</h2>
<div class="box {{ $classe }}"><strong>{{ $titulo }}.</strong> {{ $r['explicacao'] }}</div>

@if($sessoes->isNotEmpty())
<table class="lista">
    <tr><th>Login (PPPoE)</th><th>MAC do equipamento</th><th>IP atribuído</th><th>Início da sessão</th><th>Fim da sessão</th></tr>
    @foreach($sessoes as $s)
    <tr>
        <td class="mono">{{ $s['login'] }}</td><td class="mono">{{ $s['mac'] ?? '—' }}</td><td class="mono">{{ $s['ip'] }}</td>
        <td>{{ $dt($s['inicio']) }}@if($s['origem_inicio'] === 'api')<br><span class="nota">(sessão já ativa na coleta; início pelo tempo de conexão informado pelo roteador)</span>@endif</td>
        <td>{{ $s['fim'] ? $dt($s['fim']) : 'em andamento na emissão' }}@if(($s['origem_fim'] ?? null) === 'sucessora')<br><span class="nota">(fim inferido pelo login seguinte)</span>@endif</td>
    </tr>
    @endforeach
</table>
<p class="nota">A qualificação do titular (nome, CPF/CNPJ, endereço) corresponde ao cadastro do login acima no sistema de gestão do provedor.</p>
@endif

@if(!empty($r['nat']))
<h2>3. Tradução de endereços (CGNAT) no período</h2>
<table class="lista">
    <tr><th>Protocolo</th><th>Origem privada</th><th>Origem pública</th><th>Abertura</th><th>Encerramento</th><th>Cobre o instante</th></tr>
    @foreach($r['nat'] as $n)
    <tr>
        <td>{{ strtoupper($n['protocolo']) }}</td><td class="mono">{{ $n['privado'] }}</td><td class="mono">{{ $n['publico'] }}</td>
        <td>{{ $dt($n['abre']) }}</td><td>{{ $n['fecha'] ? $dt($n['fecha']) : 'sem registro de fim na janela' }}</td><td>{{ $n['cobre_instante'] ? 'sim' : 'não' }}</td>
    </tr>
    @endforeach
</table>
@endif

<h2>{{ empty($r['nat']) ? '3' : '4' }}. Metodologia</h2>
<p>O provedor compartilha endereços IPv4 públicos entre assinantes por tradução de endereços (CGNAT). O roteador de borda registra cada sessão de tradução (abertura e encerramento) com o endereço e a porta privados do assinante e o endereço e a porta públicos utilizados, sem registrar o destino acessado. O endereço privado é associado ao assinante pelos registros de autenticação PPPoE do mesmo roteador (login, endereço atribuído, MAC, início e fim da sessão). Os horários vêm do roteador e do servidor de registros, ambos sincronizados por NTP.</p>
<p class="nota">Não são guardados registros de acesso a aplicações (sites, serviços ou conteúdos acessados), conforme o art. 14 da Lei 12.965/2014.</p>

<h2>{{ empty($r['nat']) ? '4' : '5' }}. Cadeia de custódia</h2>
<p>Os registros são gravados em arquivos diários. A cada dia os arquivos são compactados e resumidos (SHA-256) num manifesto que traz o resumo do manifesto anterior e é assinado digitalmente (Ed25519). Uma cópia é enviada a um servidor externo, onde fica imutável.</p>
@if(empty($r['integridade']))
<p class="nota">Não houve arquivos diários envolvidos nesta consulta.</p>
@else
<table class="lista">
    <tr><th>Dia</th><th>Situação</th><th>Arquivos (SHA-256)</th></tr>
    @foreach($r['integridade'] as $i)
    <tr>
        <td>{{ \Illuminate\Support\Carbon::parse($i['dia'])->format('d/m/Y') }}</td>
        <td>{{ $situacoes[$i['situacao']] ?? $i['situacao'] }}<br><span class="nota">{{ $i['detalhe'] }}</span>
            @if($i['manifesto_sha256'])<br><span class="mono">manifesto {{ $i['manifesto_sha256'] }}</span>@endif</td>
        <td>@foreach($i['arquivos'] as $a)<span class="mono">{{ $a['arquivo'] }}<br>{{ $a['sha256'] ?? '—' }}</span> ({{ $arquivos[$a['situacao']] ?? $a['situacao'] }})<br>@endforeach</td>
    </tr>
    @endforeach
</table>
@endif

<h2>{{ empty($r['nat']) ? '5' : '6' }}. Fundamentação</h2>
<p class="nota">Registros de conexão mantidos pelo prazo do art. 13 da Lei 12.965/2014 (Marco Civil da Internet) e fornecidos mediante ordem judicial (art. 10, §1º e art. 22). Dados cadastrais podem ser requisitados pelas autoridades com competência legal (art. 10, §3º). Tratamento de dados pessoais conforme a Lei 13.709/2018 (LGPD), restrito ao atendimento desta solicitação.</p>

<div class="assinatura">
    {{ $empresa['responsavel'] ?: 'Responsável técnico' }}@if($empresa['cargo_responsavel']) — {{ $empresa['cargo_responsavel'] }}@endif<br>
    {{ $empresa['razao_social'] }}
</div>
<p class="nota">Consulta registrada por {{ $gerado_por ?? '—' }} em {{ $dt($r['consultado_em']) }}.</p>
</body>
</html>
