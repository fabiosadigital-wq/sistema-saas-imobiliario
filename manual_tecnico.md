# Manual Técnico - SaaS Imobiliário

## Visão Geral
Este documento descreve a arquitetura e o funcionamento do backend do sistema SaaS Imobiliário. O projeto foi desenvolvido em PHP 8 utilizando PDO com SQLite para persistência local, fornecendo uma API RESTful responsável pelo gerenciamento de imóveis, clientes, visitas e contratos, além de métricas consolidadas para dashboards e rotinas de monitoramento.

## Estrutura do Projeto
```
/
├── index.php                # Front controller responsável pelo roteamento da API
├── config.php               # Configurações de aplicação, banco e autenticação
├── functions.php            # Helpers compartilhados (respostas JSON, validação, datas, etc.)
├── auth.php                 # Middleware simples de autenticação por API Key
├── manual_tecnico.md        # Este manual técnico
├── storage/                 # Local para arquivos gerados (ex.: banco SQLite)
├── app/
│   ├── modules/             # Camada de domínio (repositórios e serviços)
│   │   ├── Database.php
│   │   ├── PropertyRepository.php
│   │   ├── ClientRepository.php
│   │   ├── VisitRepository.php
│   │   ├── ContractRepository.php
│   │   └── DashboardService.php
│   └── cron/
│       └── notify_visits.php # Rotina diária de alerta de visitas e contratos
└── vendor/                  # Reservado para dependências futuras (mantido vazio)
```

## Configuração Inicial
1. **Requisitos:** PHP 8.1+, extensão `pdo_sqlite` habilitada e servidor web compatível (Apache, Nginx ou PHP built-in server).
2. **Variáveis de ambiente (opcional):**
   - `SAAS_IMOBILIARIO_API_KEY`: API Key utilizada nas requisições autenticadas. Caso não seja definida, o valor padrão `local-dev-key` é utilizado.
3. **Permissões de diretório:** garanta que a pasta `storage/` possua permissão de escrita para o usuário do servidor.
4. **Instalação:** não há dependências externas. Basta clonar o repositório e iniciar o servidor HTTP apontando para `index.php`.

### Servidor de desenvolvimento
```
php -S 0.0.0.0:8000 index.php
```
A aplicação será exposta em `http://localhost:8000`. Todas as rotas da API estão sob o prefixo `/api`.

## Segurança e Autenticação
- A autenticação utiliza um cabeçalho `Authorization` com esquema `Bearer` contendo a API Key.
- Também é possível enviar a API Key via query string `?api_key=...`.
- Se a chave configurada estiver vazia, a autenticação é desabilitada (modo desenvolvimento).

## Banco de Dados
O banco é criado automaticamente via `PDO` ao iniciar a aplicação. As tabelas são:

### `properties`
| Coluna          | Tipo   | Descrição                                      |
|-----------------|--------|-----------------------------------------------|
| id              | INT    | Identificador único                            |
| code            | TEXT   | Código único gerado automaticamente           |
| title           | TEXT   | Título do imóvel                              |
| description     | TEXT   | Descrição detalhada                           |
| type            | TEXT   | Tipo (residencial, comercial, etc.)           |
| status          | TEXT   | available, rented, sold, archived             |
| price           | REAL   | Valor de venda/aluguel                        |
| condo_fee       | REAL   | Taxa de condomínio                            |
| city/state/...  | TEXT   | Localização                                    |
| bedrooms/...    | INT    | Características físicas                       |
| owner_*         | TEXT   | Dados do proprietário                         |
| created_at      | TEXT   | Timestamp ISO8601                             |
| updated_at      | TEXT   | Timestamp ISO8601                             |

### `clients`
- Armazena dados de clientes compradores, locatários ou proprietários.
- Campos principais: `name`, `email`, `phone`, `type` (buyer, tenant, investor, owner), `stage` (new, nurturing, negotiation, won, lost), `preferences`, `notes`.

### `visits`
- Relaciona imóveis e clientes para controle de visitas agendadas, realizadas ou canceladas.
- Campos principais: `property_id`, `client_id`, `scheduled_at`, `status`, `notes`.

### `contracts`
- Controla contratos de venda ou locação.
- Campos principais: `property_id`, `client_id`, `type` (sale, rent, management), `start_date`, `end_date`, `value`, `status`, `notes`.

## API REST
Todas as respostas são em JSON e aceitam/retornam UTF-8. Utilize o cabeçalho `Content-Type: application/json` para requisições com corpo.

### Saúde do sistema
- `GET /health` → `{ "status": "ok", "timestamp": "..." }`

### Imóveis (`/api/properties`)
- `GET /api/properties` — lista paginada com filtros `status`, `city`, `min_price`, `max_price`, `order` (`price_asc`, `price_desc`, `newest`, `oldest`).
- `POST /api/properties` — cria um imóvel. Campos obrigatórios: `title`, `type`, `price`.
- `GET /api/properties/{id}` — detalha um imóvel.
- `PUT/PATCH /api/properties/{id}` — atualiza dados parciais ou completos.
- `DELETE /api/properties/{id}` — remove o registro (cascateia visitas/contratos relacionados).

### Clientes (`/api/clients`)
- `GET /api/clients` — filtros `type`, `stage`, `search` (nome ou e-mail).
- `POST /api/clients` — cria cliente (campo obrigatório: `name`).
- `GET /api/clients/{id}` — detalhes.
- `PUT/PATCH /api/clients/{id}` — atualização.
- `DELETE /api/clients/{id}` — exclusão.

### Visitas (`/api/visits`)
- `GET /api/visits` — filtros `status`, `from`, `to` (datas ISO8601).
- `POST /api/visits` — cria visita (obrigatórios: `property_id`, `client_id`, `scheduled_at`). Valida existência dos relacionamentos.
- `GET /api/visits/{id}` — detalhes com nome do cliente e título do imóvel.
- `PUT/PATCH /api/visits/{id}` — atualiza status, data ou observações.
- `DELETE /api/visits/{id}` — remove.

### Contratos (`/api/contracts`)
- `GET /api/contracts` — filtros `status`, `type`.
- `POST /api/contracts` — cria contrato (obrigatórios: `property_id`, `client_id`, `type`, `start_date`, `value`).
- `GET /api/contracts/{id}` — detalhes.
- `PUT/PATCH /api/contracts/{id}` — atualiza.
- `DELETE /api/contracts/{id}` — remove.

### Dashboard (`/api/dashboard`)
Retorna:
- Totais de imóveis, clientes, visitas e contratos.
- Distribuição de imóveis por status e clientes por estágio.
- Visitas agendadas para os próximos `visit_days` (padrão 7).
- Contratos com término previsto nos próximos `contract_days` (padrão 30).
- Resumo de receita agregada por mês dos contratos ativos/concluídos (últimos 6 meses).

Parâmetros opcionais: `visit_days`, `contract_days` (inteiros via query string).

## Rotinas Automatizadas (Cron)
`php app/cron/notify_visits.php` — gera um relatório JSON contendo visitas agendadas e contratos prestes a expirar, útil para integrações com serviços de e-mail ou bots. Agende a execução diária via cron do sistema operacional:
```
0 7 * * * php /caminho/do/projeto/app/cron/notify_visits.php >> /var/log/saas_imobiliario_cron.log
```

## Tratamento de Erros
- Erros de validação retornam HTTP 422 com campo `details` ou `fields`.
- Erros de autenticação retornam HTTP 401.
- Recurso inexistente retorna HTTP 404.
- Métodos não suportados retornam HTTP 405 com cabeçalho `Allow` adequado.

## Boas Práticas e Próximos Passos
- Implementar testes automatizados (PHPUnit) para garantir a integridade das regras de negócio.
- Configurar logs estruturados (ex.: Monolog) e observabilidade.
- Externalizar a camada de persistência para MySQL/PostgreSQL em produção.
- Integrar serviços de e-mail/SMS para notificações de visitas e contratos.
- Adicionar autenticação multiusuário e controles de permissão refinados.

## Contato
Para dúvidas ou contribuições, utilize o repositório oficial ou entre em contato com o time de desenvolvimento.
