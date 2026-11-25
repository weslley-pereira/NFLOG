<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Verificar relatório</title>
<style>
    :root { --fundo: #f4f6f9; --cartao: #fff; --texto: #1f2933; --suave: #52606d; --borda: #d5dde6; --ok: #2f9e5b; --ruim: #c0392b; --destaque: #0f2a44; }
    @media (prefers-color-scheme: dark) { :root { --fundo: #0b1017; --cartao: #121a24; --texto: #e4e9f0; --suave: #93a1b0; --borda: #243241; --destaque: #8cb8e8; } }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--fundo); color: var(--texto); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { max-width: 640px; margin: 0 auto; padding: 32px 16px; }
    h1 { font-size: 20px; color: var(--destaque); margin: 0 0 4px; }
    .sub { color: var(--suave); font-size: 13px; margin-bottom: 20px; }
    .cartao { background: var(--cartao); border: 1px solid var(--borda); border-radius: 12px; padding: 18px; margin-bottom: 16px; }
    dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px; margin: 0; }
    dt { color: var(--suave); font-size: 13px; }
    dd { margin: 0; word-break: break-all; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
    .selo { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; }
    .selo.ok { background: color-mix(in srgb, var(--ok) 15%, transparent); color: var(--ok); }
    .selo.ruim { background: color-mix(in srgb, var(--ruim) 15%, transparent); color: var(--ruim); }
    form { display: flex; gap: 8px; }
    input[type=text] { flex: 1; padding: 9px 11px; border: 1px solid var(--borda); border-radius: 8px; background: var(--fundo); color: var(--texto); font: inherit; text-transform: uppercase; }
    button { padding: 9px 16px; border: 0; border-radius: 8px; background: #1d6fd1; color: #fff; font: inherit; cursor: pointer; }
    #resultado { margin-top: 12px; }
    .nota { color: var(--suave); font-size: 12px; }
</style>
</head>
<body>
<main>
    <h1>Verificação de relatório</h1>
    <div class="sub">{{ $empresa['razao_social'] ?: 'Provedor' }}@if($empresa['cnpj']) · CNPJ {{ $empresa['cnpj'] }}@endif · NF Log</div>

    @if($consulta)
    <div class="cartao">
        <p style="margin-top:0"><span class="selo ok">Relatório emitido por este provedor</span></p>
        <dl>
            <dt>Número</dt><dd class="mono">{{ $numero }}</dd>
            <dt>Emitido em</dt><dd>{{ $consulta->updated_at->setTimezone(config('app.timezone'))->format('d/m/Y H:i') }} (horário de Brasília)</dd>
            <dt>Código</dt><dd class="mono">{{ $consulta->codigo_verificacao }}</dd>
            <dt>SHA-256 registrado</dt><dd class="mono">{{ $consulta->relatorio_sha256 }}</dd>
        </dl>
    </div>
    <div class="cartao">
        <strong>Conferir o arquivo recebido</strong>
        <p class="nota">Escolha o PDF: o resumo é calculado aqui no seu navegador e comparado com o registrado. O arquivo não é enviado.</p>
        <input type="file" id="arquivo" accept="application/pdf">
        <div id="resultado"></div>
    </div>
    @elseif($codigo !== '')
    <div class="cartao"><span class="selo ruim">Código não encontrado</span><p class="nota">Confira o código impresso no relatório (formato XXXX-XXXX-XXXX-XXXX).</p></div>
    @endif

    <div class="cartao">
        <form method="get" action="/verificar">
            <input type="text" name="codigo" value="{{ $codigo }}" placeholder="Código do relatório" maxlength="24" autocomplete="off">
            <button type="submit">Verificar</button>
        </form>
    </div>
    <p class="nota">Esta página confirma apenas a origem e a integridade do relatório. O conteúdo é entregue somente à autoridade solicitante.</p>
</main>
@if($consulta)
<script>
    const esperado = @json($consulta->relatorio_sha256);
    document.getElementById('arquivo').addEventListener('change', async (e) => {
        const f = e.target.files[0], saida = document.getElementById('resultado');
        if (!f) return;
        const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await f.arrayBuffer())))
            .map((b) => b.toString(16).padStart(2, '0')).join('');
        saida.innerHTML = hash === esperado
            ? '<span class="selo ok">Confere: arquivo idêntico ao emitido</span>'
            : '<span class="selo ruim">Não confere: o arquivo foi alterado ou é outro relatório</span>';
        saida.insertAdjacentHTML('beforeend', '<p class="nota mono">SHA-256 do arquivo: ' + hash + '</p>');
    });
</script>
@endif
</body>
</html>
