# NF Log -- notas para quem mexe no código

- Comunicação e textos 100% em português do Brasil (código, comentários, telas).
- Produto vendido separado do NFC ERP (instalado no provedor; depois, versão hospedada). Licença e atualizações pelo nf-licencas (chave NFLOG-...).
- Nunca guardar destino de conexão nem histórico de navegação (Marco Civil, art. 14): o filtro do coletor descarta nat_event 0 e qualquer origem que não seja rede interna.
- VictoriaLogs: ao enviar por HTTP (/insert/jsonline), sempre `Content-Type: application/stream+json` -- sem isso o corpo é lido como formulário e as linhas somem com HTTP 200.
- Consulta: sem log de NAT chegando no instante, a conclusão é "sem_dados" (nunca supor sessão aberta).
