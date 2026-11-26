# NF Log -- notas para quem mexe no código

- Comunicação e textos 100% em português do Brasil (código, comentários, telas).
- Produto vendido separado do NFC ERP (instalado no provedor; depois, versão hospedada). Licença e atualizações pelo nf-licencas (chave NFLOG-...).
- Nunca guardar destino de conexão nem histórico de navegação (Marco Civil, art. 14): o filtro do coletor descarta nat_event 0 e qualquer origem que não seja rede interna.
- VictoriaLogs: ao enviar por HTTP (/insert/jsonline), sempre `Content-Type: application/stream+json` -- sem isso o corpo é lido como formulário e as linhas somem com HTTP 200.
- Consulta: sem log de NAT chegando no instante, a conclusão é "sem_dados" (nunca supor sessão aberta).
- Instalador (`deploy/instalar.sh`): testado do zero e como atualização num contêiner Debian 12 limpo (systemd-nspawn `nflog-teste` na dev, rede 192.168.251.0/24). Lições:
  - nfacctd: `nfacctd_ip: 0.0.0.0` (em socket IPv6 o remetente vira ::ffff:x e não bate com a lista de permitidos) e `plugin_buffer_size` no padrão (buffer grande segura os eventos de quem tem pouco tráfego);
  - o umask do nfacctd cria pastas 0700: o filtro faz chmod 0750 para o painel conseguir conferir a integridade;
  - nginx 1.22 (Debian 12) não conhece `http2 on;` -- usar `listen 443 ssl http2`;
  - composer/npm precisam de HOME (systemd e cron rodam sem).
- Importação de sessões PPPoE relê os últimos 5 min a cada rodada; `aplicar()` precisa continuar idempotente (mesmo roteador+login+início não é gravado de novo).
- Sistema de gestão (conectores em `app/Services/Conectores`, orquestrados por `Cadastro`): a qualificação do titular só é buscada ao REGISTRAR a consulta e só com conclusão "identificado" (pré-visualização e ambíguo não puxam dado pessoal -- LGPD, necessidade). O retrato fica em `resultado.cadastro` da consulta. NFC: `GET /api/integracao-nflog/assinante?login=&instante=` com token de serviço (ability `nflog`, gerado em Integrações > NF Log no NFC); o NFC registra cada leitura.
- Login com verificação em duas etapas (TOTP, `App\Support\Totp`): senha certa com 2FA ativo só deixa `login_pendente` na sessão (5 min); código reaproveitado é recusado (`totp_ultimo_passo`). Recuperação: `php artisan nflog:2fa-desativar <email>`. QR por chillerlan/php-qrcode (também serve para o QR do PDF).
- Verificação do relatório: código XXXX-XXXX-XXXX-XXXX + QR no PDF apontando para `NFLOG_URL_VERIFICACAO`/verificar/<código> (padrão APP_URL). Página PÚBLICA (Blade, sem assets do SPA), só número/emissão/SHA-256; o PDF é conferido no navegador. Se o painel for restrito por IP, liberar só `/verificar` no nginx.
- Licença (nf-licencas, chave NFLOG-..., `App\Services\Licenca`): sem licença válida só NOVAS consultas são bloqueadas (coleta e relatórios antigos seguem); central fora do ar = vale a última verificação boa por 15 dias. `nflog:licenca` (diário 03:17, `--chave=` grava), `nflog:sinal` (5 min, manda `PainelController::problemas()`). Na dev a licença de teste está REVOGADA (consultas bloqueadas): criar outra se precisar testar consulta.
- Atualização remota (1.1.0+): `deploy/construir-pacote.sh` gera dist/nflog-X.Y.Z.tar.gz (versão de config/nflog.php, tela compilada, arquivo VERSAO); na VPS: `licenca publicar-versao nflog-X.Y.Z.tar.gz`; liberar em Licenças NF > NF Log. O sinal leva o andamento e grava pedido.json; o agente root `nflog-atualizar` (timer 1 min) confere assinatura (/etc/nflog/atualizacao.pub = pacotes.pub da central), produto, versão maior e sha256, faz backup do código e do banco e volta sozinho se o instalador falhar.
