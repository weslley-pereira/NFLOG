#!/bin/bash
# NF Log -- instalador (Debian 12 ou 13, máquina dedicada no provedor).
#
# Uso, a partir de um clone do repositório:
#   deploy/instalar.sh --admin-email voce@provedor.com.br [--admin-nome "Nome"]
#                      [--redes "10.0.0.0/8 100.64.0.0/10"] [--fuso America/Sao_Paulo]
#
# Monta tudo o que o painel precisa:
#  - rsyslog: recebe o syslog dos roteadores (514 UDP/TCP) e grava o bruto;
#  - nfacctd + nflog-nat-filtrar: log de NAT por IPFIX (2055 UDP), sem destino;
#  - VictoriaLogs: pesquisa dos registros (só em 127.0.0.1);
#  - nflog-fechar-dia: compacta, resume (SHA-256) e assina cada dia, e copia
#    para fora se configurado; nflog-vigiar avisa se o NAT parar;
#  - painel (Laravel) em nginx + php-fpm, HTTPS com certificado próprio.
#
# Pode rodar de novo para atualizar: mantém .env, banco, chaves e registros.
set -euo pipefail

REPO=$(cd "$(dirname "$0")/.." && pwd)
APP=/var/www/nflog
DADOS=/var/lib/nflog
CONF=/etc/nflog/nflog.conf
VL_VERSAO=v1.53.0
NODE_VERSAO=v20.20.2

ADMIN_EMAIL="" ADMIN_NOME="Administrador" ARG_REDES="" ARG_FUSO=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --admin-email) ADMIN_EMAIL=$2; shift 2 ;;
        --admin-nome)  ADMIN_NOME=$2; shift 2 ;;
        --redes)       ARG_REDES=$2; shift 2 ;;
        --fuso)        ARG_FUSO=$2; shift 2 ;;
        -h|--help)     sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opção desconhecida: $1" >&2; exit 1 ;;
    esac
done

passo() { echo; echo "==> $*"; }
falha() { echo "ERRO: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || falha "rode como root"
. /etc/os-release
case "${VERSION_ID:-}" in
    12) PHP=8.3 ;;
    13) PHP=8.4 ;;
    *) falha "suportado só Debian 12 ou 13 (encontrado: ${PRETTY_NAME:-desconhecido})" ;;
esac
case "$(dpkg --print-architecture)" in
    amd64) ARQ_VL=amd64 ARQ_NODE=x64 ;;
    arm64) ARQ_VL=arm64 ARQ_NODE=arm64 ;;
    *) falha "arquitetura não suportada: $(dpkg --print-architecture)" ;;
esac
export DEBIAN_FRONTEND=noninteractive
export HOME=${HOME:-/root}   # composer e npm precisam (systemd/cron rodam sem)

# ---------------------------------------------------------------- configuração
passo "Configuração (/etc/nflog)"
install -d -m 0755 /etc/nflog
if [[ ! -f $CONF ]]; then
    install -m 0644 "$REPO/deploy/nflog.conf.exemplo" "$CONF"
    [[ -n $ADMIN_EMAIL ]] || falha "primeira instalação: informe --admin-email"
fi
[[ -n $ARG_REDES ]] && sed -i "s|^REDES_PERMITIDAS=.*|REDES_PERMITIDAS=\"$ARG_REDES\"|" "$CONF"
[[ -n $ARG_FUSO ]] && sed -i "s|^FUSO=.*|FUSO=$ARG_FUSO|" "$CONF"
# shellcheck source=/dev/null
. "$CONF"
[[ -e /usr/share/zoneinfo/$FUSO ]] || falha "fuso inválido: $FUSO"

# -------------------------------------------------------------------- pacotes
passo "Pacotes do sistema"
apt-get update -q
apt-get install -y -q ca-certificates curl gnupg rsyslog pmacct zstd rsync python3 \
    openssh-client nginx sqlite3 unzip cron xz-utils systemd-timesyncd >/dev/null
if [[ $VERSION_ID == 12 && ! -f /etc/apt/sources.list.d/php-sury.list ]]; then
    # PHP 8.3 no Debian 12 vem do repositório do mantenedor do PHP no Debian.
    curl -fsSLo /tmp/sury-chave.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
    dpkg -i /tmp/sury-chave.deb >/dev/null && rm -f /tmp/sury-chave.deb
    echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php-sury.list
    apt-get update -q
fi
apt-get install -y -q php$PHP-fpm php$PHP-cli php$PHP-sqlite3 php$PHP-mbstring php$PHP-xml \
    php$PHP-curl php$PHP-zip php$PHP-gd php$PHP-intl php$PHP-bcmath >/dev/null

for rede in $REDES_PERMITIDAS; do
    python3 -c "import ipaddress,sys; ipaddress.ip_network(sys.argv[1])" "$rede" 2>/dev/null || falha "rede inválida em REDES_PERMITIDAS: $rede"
done

# Relógio certo é prova: o ofício pergunta por um segundo exato.
timedatectl set-timezone "$FUSO" 2>/dev/null || ln -sf "/usr/share/zoneinfo/$FUSO" /etc/localtime
systemctl enable --now systemd-timesyncd 2>/dev/null || true

# ------------------------------------------------------- usuários e diretórios
passo "Usuários, pastas e chaves"
getent group nflog >/dev/null || groupadd --system nflog
usermod -aG nflog www-data
id victorialogs >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin victorialogs
install -d -m 0750 -o root -g nflog "$BASE" "$BASE/arquivo" "$BASE/manifestos"
install -d -m 0750 -o root -g root /var/spool/nflog-nat
install -d -m 0750 -o victorialogs -g victorialogs /var/lib/victoria-logs
install -d -m 0750 -o www-data -g www-data "$DADOS"

# Chave que assina os manifestos diários: fica só nesta máquina. A pública
# (assinantes-permitidos) é a que confere -- vai no relatório e na cópia externa.
if [[ ! -f /etc/nflog/assinatura ]]; then
    ssh-keygen -q -t ed25519 -N "" -C "nflog@$(hostname)" -f /etc/nflog/assinatura
fi
chmod 0600 /etc/nflog/assinatura
echo "nflog namespaces=\"nflog\" $(cut -d' ' -f1,2 /etc/nflog/assinatura.pub)" > /etc/nflog/assinantes-permitidos
chmod 0644 /etc/nflog/assinantes-permitidos
# Chave da cópia externa (só usada se COPIA_DESTINO estiver preenchido).
[[ -f /etc/nflog/envio ]] || ssh-keygen -q -t ed25519 -N "" -C "nflog-envio@$(hostname)" -f /etc/nflog/envio
chmod 0600 /etc/nflog/envio

# ----------------------------------------------------------------- VictoriaLogs
passo "VictoriaLogs $VL_VERSAO"
if ! /usr/local/bin/victoria-logs -version 2>/dev/null | grep -q -- "-$VL_VERSAO"; then
    tmp=$(mktemp -d)
    url=https://github.com/VictoriaMetrics/VictoriaLogs/releases/download/$VL_VERSAO
    curl -fsSL -o "$tmp/vl.tar.gz" "$url/victoria-logs-linux-$ARQ_VL-$VL_VERSAO.tar.gz"
    curl -fsSL -o "$tmp/vl.sum" "$url/victoria-logs-linux-$ARQ_VL-${VL_VERSAO}_checksums.txt"
    grep -q "$(sha256sum "$tmp/vl.tar.gz" | cut -d' ' -f1)" "$tmp/vl.sum" || falha "checksum do VictoriaLogs não confere"
    tar -xzf "$tmp/vl.tar.gz" -C "$tmp"
    install -m 0755 "$tmp/victoria-logs-prod" /usr/local/bin/victoria-logs
    rm -rf "$tmp"
fi
sed -e "s|@RETENCAO@|$RETENCAO_DIAS|" -e "s|@FUSO@|$FUSO|" \
    "$REPO/deploy/systemd/victoria-logs.service" > /etc/systemd/system/victoria-logs.service

# ------------------------------------------------------------------- coletores
passo "Recepção de syslog e do log de NAT"
redes_virgula=$(echo "$REDES_PERMITIDAS" | tr -s ' ' | sed 's/ /, /g')
sed -e "s|@REDES@|$redes_virgula|g" -e "s|@BASE@|$BASE|g" \
    "$REPO/deploy/coletor/rsyslog-nflog.conf" > /etc/rsyslog.d/10-nflog.conf
rsyslogd -N1 -f /etc/rsyslog.conf >/dev/null 2>&1 || { rsyslogd -N1 -f /etc/rsyslog.conf; falha "configuração do rsyslog inválida"; }

echo "$REDES_PERMITIDAS" | tr ' ' '\n' | sed '/^$/d' > /etc/nflog/nfacctd-permitidos.txt
sed "s|@BASE@|$BASE|g" "$REPO/deploy/coletor/nfacctd.conf" > /etc/pmacct/nfacctd.conf
install -d /etc/systemd/system/nfacctd.service.d
install -m 0644 "$REPO/deploy/systemd/nfacctd-nflog.conf" /etc/systemd/system/nfacctd.service.d/nflog.conf
# O pacote pmacct traz outros coletores; só o nfacctd é usado.
for outro in pmacctd sfacctd uacctd pmbgpd pmbmpd pmtelemetryd; do
    systemctl disable --now "$outro" >/dev/null 2>&1 || true
done

for s in nflog-nat-filtrar nflog-fechar-dia nflog-vigiar; do
    install -m 0755 "$REPO/deploy/coletor/$s" "/usr/local/sbin/$s"
done
for u in nflog-fechar-dia.service nflog-fechar-dia.timer nflog-vigiar.service nflog-vigiar.timer; do
    install -m 0644 "$REPO/deploy/systemd/$u" "/etc/systemd/system/$u"
done
systemctl daemon-reload
systemctl enable --now victoria-logs >/dev/null 2>&1
systemctl restart victoria-logs rsyslog
systemctl enable nfacctd >/dev/null 2>&1
systemctl restart nfacctd
systemctl enable --now nflog-fechar-dia.timer nflog-vigiar.timer >/dev/null 2>&1

# ---------------------------------------------------------------------- painel
passo "Painel (código em $APP)"
if [[ "$REPO" != "$APP" ]]; then
    install -d -m 0755 "$APP"
    rsync -a --delete --exclude=.git --exclude=/.env --exclude=/storage/ --exclude=/vendor/ \
        --exclude=/node_modules/ --exclude=/public/build/ --exclude=/bootstrap/cache/ \
        --exclude=/database/database.sqlite "$REPO/" "$APP/"
fi
install -d -m 0755 "$APP/bootstrap/cache"
for d in app/private app/public framework/cache/data framework/sessions framework/views logs; do
    install -d "$APP/storage/$d"
done
chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"

if [[ ! -x /usr/local/bin/composer ]]; then
    tmp=$(mktemp -d)
    curl -fsSL -o "$tmp/composer.phar" https://getcomposer.org/download/latest-stable/composer.phar
    curl -fsSL -o "$tmp/composer.sum" https://getcomposer.org/download/latest-stable/composer.phar.sha256sum
    [[ "$(sha256sum "$tmp/composer.phar" | cut -d' ' -f1)" == "$(cut -d' ' -f1 "$tmp/composer.sum")" ]] || falha "checksum do composer não confere"
    install -m 0755 "$tmp/composer.phar" /usr/local/bin/composer
    rm -rf "$tmp"
fi
(cd "$APP" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet)

# Tela (Vue): compilada aqui com um Node temporário, que é apagado depois.
if [[ ! -f "$REPO/public/build/manifest.json" ]]; then
    tmp=$(mktemp -d)
    pacote=node-$NODE_VERSAO-linux-$ARQ_NODE.tar.xz
    curl -fsSL -o "$tmp/$pacote" "https://nodejs.org/dist/$NODE_VERSAO/$pacote"
    curl -fsSL -o "$tmp/SHASUMS256.txt" "https://nodejs.org/dist/$NODE_VERSAO/SHASUMS256.txt"
    (cd "$tmp" && grep " $pacote\$" SHASUMS256.txt | sha256sum -c --quiet) || falha "checksum do Node não confere"
    tar -xJf "$tmp/$pacote" -C "$tmp"
    (cd "$APP" && PATH="$tmp/node-$NODE_VERSAO-linux-$ARQ_NODE/bin:$PATH" npm ci --no-audit --no-fund --loglevel=error \
        && PATH="$tmp/node-$NODE_VERSAO-linux-$ARQ_NODE/bin:$PATH" npm run build --silent >/dev/null)
    rm -rf "$tmp" "$APP/node_modules"
else
    rsync -a --delete "$REPO/public/build/" "$APP/public/build/"
fi

IP_PRINCIPAL=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1)
IP_PRINCIPAL=${IP_PRINCIPAL:-127.0.0.1}
if [[ ! -f "$APP/.env" ]]; then
    cat > "$APP/.env" <<EOF
APP_NAME="NF Log"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://$IP_PRINCIPAL
APP_TIMEZONE=$FUSO
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$DADOS/nflog.sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
SANCTUM_STATEFUL_DOMAINS=$IP_PRINCIPAL,$(hostname),$(hostname -f 2>/dev/null || hostname),localhost
NFLOG_LOGS=$BASE
NFLOG_FUSO=$FUSO
EOF
    chown root:www-data "$APP/.env"
    chmod 0640 "$APP/.env"
fi
[[ -f $DADOS/nflog.sqlite ]] || install -m 0640 -o www-data -g www-data /dev/null "$DADOS/nflog.sqlite"

artisan() { (cd "$APP" && runuser -u www-data -- php artisan "$@"); }
grep -q '^APP_KEY=base64:' "$APP/.env" || (cd "$APP" && php artisan key:generate --force --quiet)
artisan migrate --force --quiet
artisan optimize --quiet

echo "* * * * * www-data cd $APP && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/nflog

# --------------------------------------------------------------- nginx e HTTPS
passo "nginx (HTTPS)"
install -d -m 0750 /etc/nflog/tls
if [[ ! -f /etc/nflog/tls/nflog.crt ]]; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=$(hostname)" \
        -addext "subjectAltName=IP:$IP_PRINCIPAL,DNS:$(hostname)" \
        -keyout /etc/nflog/tls/nflog.key -out /etc/nflog/tls/nflog.crt 2>/dev/null
    chmod 0600 /etc/nflog/tls/nflog.key
fi
sed -e "s|@APP@|$APP|g" -e "s|@PHP@|$PHP|g" "$REPO/deploy/web/nginx-nflog.conf" > /etc/nginx/sites-available/nflog
ln -sf /etc/nginx/sites-available/nflog /etc/nginx/sites-enabled/nflog
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
systemctl enable --now php$PHP-fpm nginx cron >/dev/null 2>&1
systemctl restart php$PHP-fpm   # pega o grupo nflog do www-data
systemctl reload nginx

# ---------------------------------------------------------------- administrador
SENHA_ADMIN=""
if [[ "$(sqlite3 "$DADOS/nflog.sqlite" 'select count(*) from users')" == 0 ]]; then
    passo "Administrador"
    [[ -n $ADMIN_EMAIL ]] || falha "nenhum usuário no painel: informe --admin-email"
    SENHA_ADMIN=$(artisan nflog:usuario "$ADMIN_EMAIL" "$ADMIN_NOME" | sed -n 's/^Senha: \([^ ]*\).*/\1/p')
fi

# ---------------------------------------------------------------------- resumo
passo "Conferência"
for s in victoria-logs rsyslog nfacctd php$PHP-fpm nginx; do
    printf '  %-16s %s\n' "$s" "$(systemctl is-active "$s")"
done
cat <<EOF

NF Log instalado.

  Painel:        https://$IP_PRINCIPAL  (certificado próprio: o navegador vai avisar na primeira vez)
EOF
if [[ -n $SENHA_ADMIN ]]; then
    echo "  Usuário:       $ADMIN_EMAIL"
    echo "  Senha:         $SENHA_ADMIN   (anote agora; ela não fica guardada)"
fi
cat <<EOF

  Recebe syslog em $IP_PRINCIPAL:514 (UDP/TCP) e log de NAT em $IP_PRINCIPAL:2055 (IPFIX),
  só das redes: $REDES_PERMITIDAS

Próximos passos:
  1. No painel, Configurações: dados da empresa, IP deste coletor ($IP_PRINCIPAL) e os roteadores.
  2. Baixe o script do Mikrotik em Configurações e importe em cada roteador.
  3. Cópia externa (recomendada): preencha COPIA_DESTINO em $CONF e libere no
     destino esta chave, só com escrita (rrsync -wo -no-del):
     $(cat /etc/nflog/envio.pub)
  4. Guarde fora daqui a chave pública que confere os manifestos:
     $(cut -d' ' -f1,2 /etc/nflog/assinatura.pub)
EOF
