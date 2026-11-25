# NF Log

Coletor de registros para provedores de internet: **log de NAT do CGNAT**, **sessões PPPoE** e **syslog** dos equipamentos, com **consulta por IP/porta/instante** e **relatório em PDF** para responder ofícios e ordens judiciais.

- Cadeia: IP público + porta + instante → evento de NAT (IPFIX do Mikrotik, sem destino) → IP privado → sessão PPPoE (login, MAC) → assinante (conector com o sistema de gestão).
- Cadeia de custódia: arquivos diários compactados, resumidos (SHA-256) em manifesto encadeado e assinado (Ed25519), com cópia externa imutável.
- Não guarda destino/conteúdo acessado (art. 14 da Lei 12.965/2014).

Pilha: Laravel 13 + Vue 3 + SQLite; rsyslog, VictoriaLogs e nfacctd (pmacct) no coletor. Instalação: `deploy/instalar.sh` (Debian 12/13).
