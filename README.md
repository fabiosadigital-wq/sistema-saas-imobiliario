# Sistema SaaS Imobiliário

Backend em PHP voltado para imobiliárias gerenciarem imóveis, clientes, visitas e contratos. A aplicação expõe uma API RESTful e uma rotina de cron para alertas de visitas e contratos prestes a vencer. Consulte o [Manual Técnico](manual_tecnico.md) para detalhes completos de arquitetura, endpoints e configuração.

## Requisitos
- PHP 8.1+
- Extensão `pdo_sqlite`

## Como executar
```bash
php -S 0.0.0.0:8000 index.php
```

As rotas estarão disponíveis em `http://localhost:8000`. Utilize o cabeçalho `Authorization: Bearer <API_KEY>` (valor padrão: `local-dev-key`).

## Rotina de monitoramento
```bash
php app/cron/notify_visits.php
```

O comando gera um JSON com visitas agendadas e contratos próximos do vencimento.

## Próximos passos sugeridos
- Adicionar testes automatizados.
- Configurar logs estruturados.
- Migrar banco de dados para MySQL/PostgreSQL em produção.
