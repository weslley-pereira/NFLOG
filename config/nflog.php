<?php

/*
 * NF Log -- coletor de logs para provedores (registros de conexão, NAT do
 * CGNAT, syslog dos equipamentos) com consulta e relatório para ofícios.
 * Os caminhos seguem o que o instalador (deploy/instalar.sh) monta.
 */
return [
    // Arquivo bruto: <base>/arquivo/<IP de origem>/<AAAA-MM-DD>.log(.zst) e
    // <base>/manifestos/<AAAA-MM-DD>.txt(.sig), fechados e assinados todo dia.
    'logs_base' => env('NFLOG_LOGS', '/srv/logs'),

    // Chave pública que confere os manifestos (formato allowed_signers do
    // ssh-keygen) e a identidade usada na assinatura.
    'assinantes_permitidos' => env('NFLOG_ASSINANTES', '/etc/nflog/assinantes-permitidos'),
    'assinatura_identidade' => env('NFLOG_ASSINATURA_ID', 'nflog'),
    'assinatura_namespace' => env('NFLOG_ASSINATURA_NS', 'nflog'),

    // VictoriaLogs (pesquisa). Usuário/senha só quando ele fica atrás de
    // um proxy com autenticação (desenvolvimento).
    'victorialogs' => [
        'url' => env('NFLOG_VL_URL', 'http://127.0.0.1:9428'),
        'usuario' => env('NFLOG_VL_USUARIO'),
        'senha' => env('NFLOG_VL_SENHA'),
    ],

    // Endereço PÚBLICO da página /verificar impressa no relatório (QR Code).
    // O painel pode ficar só na rede interna; esta página precisa abrir de fora.
    'url_verificacao' => env('NFLOG_URL_VERIFICACAO', env('APP_URL')),

    // Fuso em que os ofícios costumam vir (horário de Brasília).
    'fuso' => env('NFLOG_FUSO', 'America/Sao_Paulo'),

    // Quanto antes do instante pedido procurar a abertura da sessão de NAT
    // (TCP estabelecido pode durar horas no conntrack do Mikrotik).
    'janela_nat_horas' => (int) env('NFLOG_JANELA_NAT_HORAS', 24),

    // Folga para diferença de relógio entre quem registrou o fato (a
    // plataforma do ofício) e o roteador.
    'tolerancia_segundos' => (int) env('NFLOG_TOLERANCIA_S', 60),
];
