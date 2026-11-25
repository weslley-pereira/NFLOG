# NF Log

Coletor de registros para provedores de internet: **log de NAT do CGNAT**, **sessões PPPoE** e **syslog** dos equipamentos, com **consulta por IP/porta/instante** e **relatório em PDF** para responder ofícios e ordens judiciais.

- Cadeia: IP público + porta + instante → evento de NAT (IPFIX do Mikrotik, sem destino) → IP privado → sessão PPPoE (login, MAC) → assinante (conector com o sistema de gestão).
- Cadeia de custódia: arquivos diários compactados, resumidos (SHA-256) em manifesto encadeado e assinado (Ed25519), com cópia externa opcional.
- Não guarda destino/conteúdo acessado (art. 14 da Lei 12.965/2014).

Pilha: Laravel 13 + Vue 3 + SQLite; rsyslog, VictoriaLogs e nfacctd (pmacct) no coletor.

## Instalação (no provedor)

Máquina dedicada (VM ou física) com **Debian 12 ou 13** limpo. Para um provedor pequeno/médio: 2 vCPU, 2 GB de RAM e disco para 1 ano de registros (como referência, ~18 mil eventos de NAT/min dão 40–55 GB/ano).

```bash
git clone <repositório> /opt/nflog-fonte
/opt/nflog-fonte/deploy/instalar.sh --admin-email voce@provedor.com.br --admin-nome "Seu Nome" \
    --redes "10.0.0.0/8 100.64.0.0/10"      # redes dos roteadores que vão mandar registros
```

O instalador mostra no fim o endereço do painel e a senha do administrador (uma vez só). Depois:

1. **Configurações** no painel: dados da empresa (saem no relatório), IP deste coletor e os roteadores.
2. **Baixar script do Mikrotik** e importar em cada roteador (`/import nflog-mikrotik.rsc`): liga o syslog e o log de NAT (Traffic Flow/IPFIX sem destino).
3. Recomendado: cópia externa (`COPIA_DESTINO` em `/etc/nflog/nflog.conf`) e aviso no Telegram (`TELEGRAM_TOKEN`/`TELEGRAM_CHAT`).

Para atualizar: `git -C /opt/nflog-fonte pull` e rodar o instalador de novo (mantém `.env`, banco, chaves e registros).

## Onde fica cada coisa

| O quê | Onde |
|---|---|
| Configuração do coletor | `/etc/nflog/nflog.conf` |
| Registros brutos (por IP de origem; NAT em `nat-<IP do roteador>`) | `/srv/nflog/arquivo/` |
| Manifestos diários assinados | `/srv/nflog/manifestos/` |
| Chave que assina / chave pública que confere | `/etc/nflog/assinatura` / `/etc/nflog/assinantes-permitidos` |
| Painel | `/var/www/nflog` (banco em `/var/lib/nflog/nflog.sqlite`) |
| Serviços | `rsyslog`, `nfacctd`, `victoria-logs`, `nflog-fechar-dia.timer` (00:20), `nflog-vigiar.timer` (10 min) |

Conferir um dia fora do sistema:

```bash
ssh-keygen -Y verify -f /etc/nflog/assinantes-permitidos -I nflog -n nflog \
    -s /srv/nflog/manifestos/AAAA-MM-DD.txt.sig < /srv/nflog/manifestos/AAAA-MM-DD.txt
cd /srv/nflog/arquivo && grep -v '^#\|^anterior' ../manifestos/AAAA-MM-DD.txt | sha256sum -c
```
