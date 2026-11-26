#!/bin/bash
# NF Log -- gera o pacote de atualização remota (nflog-X.Y.Z.tar.gz) a partir
# do commit atual, com a tela já compilada (o servidor do parceiro não precisa
# de Node). A versão vem de config/nflog.php.
#
# Publicar (na VPS da central, como root):
#   licenca publicar-versao nflog-X.Y.Z.tar.gz [--notas=arquivo]
# Depois libere para cada servidor em Licenças NF > aba NF Log.
set -euo pipefail
cd "$(dirname "$0")/.."

[[ -z "$(git status --porcelain)" ]] || { echo "Há alterações não commitadas: o pacote sai do commit atual." >&2; exit 1; }
VERSAO=$(grep -oP "'versao' => '\K[0-9.]+" config/nflog.php)
[[ $VERSAO =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Versão inválida em config/nflog.php: $VERSAO" >&2; exit 1; }
NOME=nflog-$VERSAO
SAIDA=${1:-$PWD/dist}
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

git archive --format=tar --prefix="$NOME/" HEAD | tar -x -C "$TMP"
echo "$VERSAO+$(date +%Y%m%d).$(git rev-parse --short HEAD)" > "$TMP/$NOME/VERSAO"
(cd "$TMP/$NOME" && npm ci --no-audit --no-fund --loglevel=error >/dev/null && npm run build --silent >/dev/null && rm -rf node_modules)
[[ -f "$TMP/$NOME/public/build/manifest.json" ]] || { echo "Build da tela não saiu." >&2; exit 1; }

mkdir -p "$SAIDA"
tar -czf "$SAIDA/$NOME.tar.gz" -C "$TMP" "$NOME"
echo "Pacote: $SAIDA/$NOME.tar.gz ($(cat "$TMP/$NOME/VERSAO"), $(du -h "$SAIDA/$NOME.tar.gz" | cut -f1))"
