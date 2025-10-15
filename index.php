<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Modules\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/modules/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use App\Modules\ClientRepository;
use App\Modules\ContractRepository;
use App\Modules\DashboardService;
use App\Modules\Database;
use App\Modules\PropertyRepository;
use App\Modules\VisitRepository;

// Configurações básicas
$timezone = $config['app']['timezone'] ?? 'UTC';
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set($timezone);
}

$pdo = Database::connection($config['database']);
Database::migrate($pdo);

$propertyRepository = new PropertyRepository($pdo);
$clientRepository = new ClientRepository($pdo);
$visitRepository = new VisitRepository($pdo);
$contractRepository = new ContractRepository($pdo);
$dashboardService = new DashboardService(
    $pdo,
    $propertyRepository,
    $clientRepository,
    $visitRepository,
    $contractRepository
);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '') {
    json_response([
        'name' => $config['app']['name'] ?? 'SaaS Imobiliário',
        'version' => '1.0.0',
        'documentation' => '/manual_tecnico.md',
        'endpoints' => [
            'GET /health',
            'GET /api/properties',
            'POST /api/properties',
            'GET /api/clients',
            'GET /api/visits',
            'GET /api/contracts',
            'GET /api/dashboard',
        ],
    ]);
    return;
}

if ($path === '/health') {
    json_response(['status' => 'ok', 'timestamp' => now()]);
    return;
}

if (strpos($path, '/api/') !== 0) {
    respond_not_found('Rota');
    return;
}

ensure_authenticated($config);

$segments = array_values(array_filter(explode('/', substr($path, 5))));
$resource = $segments[0] ?? null;
$identifier = isset($segments[1]) ? (int) $segments[1] : null;

switch ($resource) {
    case 'properties':
        handleProperties($propertyRepository, $method, $identifier);
        break;
    case 'clients':
        handleClients($clientRepository, $method, $identifier);
        break;
    case 'visits':
        handleVisits($visitRepository, $method, $identifier);
        break;
    case 'contracts':
        handleContracts($contractRepository, $method, $identifier);
        break;
    case 'dashboard':
        if ($method !== 'GET') {
            respond_method_not_allowed(['GET']);
            return;
        }

        $visitsWindow = isset($_GET['visit_days']) ? (int) $_GET['visit_days'] : (int) ($config['dashboard']['upcoming_visit_days'] ?? 7);
        $contractsWindow = isset($_GET['contract_days']) ? (int) $_GET['contract_days'] : (int) ($config['dashboard']['expiring_contract_days'] ?? 30);

        json_response($dashboardService->metrics($visitsWindow, $contractsWindow));
        break;
    default:
        respond_not_found('Rota');
        break;
}

function handleProperties(PropertyRepository $repository, string $method, ?int $id): void
{
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $property = $repository->find($id);
                if ($property === null) {
                    respond_not_found('Imóvel');
                    return;
                }

                json_response($property);
                return;
            }

            $pagination = build_pagination($_GET);
            $filters = [
                'status' => $_GET['status'] ?? null,
                'city' => $_GET['city'] ?? null,
                'min_price' => $_GET['min_price'] ?? null,
                'max_price' => $_GET['max_price'] ?? null,
                'order' => $_GET['order'] ?? null,
            ];

            json_response($repository->all($filters, $pagination));
            return;
        case 'POST':
            $payload = get_json_input();
            json_response($repository->create($payload), 201);
            return;
        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do imóvel para atualização.']);
                return;
            }

            $payload = get_json_input();
            $updated = $repository->update($id, $payload);
            if ($updated === null) {
                respond_not_found('Imóvel');
                return;
            }

            json_response($updated);
            return;
        case 'DELETE':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do imóvel para exclusão.']);
                return;
            }

            $repository->delete($id);
            json_response(['deleted' => true]);
            return;
        default:
            respond_method_not_allowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            return;
    }
}

function handleClients(ClientRepository $repository, string $method, ?int $id): void
{
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $client = $repository->find($id);
                if ($client === null) {
                    respond_not_found('Cliente');
                    return;
                }

                json_response($client);
                return;
            }

            $pagination = build_pagination($_GET);
            $filters = [
                'type' => $_GET['type'] ?? null,
                'stage' => $_GET['stage'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];

            json_response($repository->all($filters, $pagination));
            return;
        case 'POST':
            $payload = get_json_input();
            json_response($repository->create($payload), 201);
            return;
        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do cliente para atualização.']);
                return;
            }

            $payload = get_json_input();
            $updated = $repository->update($id, $payload);
            if ($updated === null) {
                respond_not_found('Cliente');
                return;
            }

            json_response($updated);
            return;
        case 'DELETE':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do cliente para exclusão.']);
                return;
            }

            $repository->delete($id);
            json_response(['deleted' => true]);
            return;
        default:
            respond_method_not_allowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            return;
    }
}

function handleVisits(VisitRepository $repository, string $method, ?int $id): void
{
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $visit = $repository->find($id);
                if ($visit === null) {
                    respond_not_found('Visita');
                    return;
                }

                json_response($visit);
                return;
            }

            $pagination = build_pagination($_GET);
            $filters = [
                'status' => $_GET['status'] ?? null,
                'from' => $_GET['from'] ?? null,
                'to' => $_GET['to'] ?? null,
            ];

            json_response($repository->all($filters, $pagination));
            return;
        case 'POST':
            $payload = get_json_input();
            $visit = $repository->create($payload);
            if ($visit === null) {
                respond_validation_error(['payload' => 'Não foi possível criar a visita.']);
                return;
            }

            json_response($visit, 201);
            return;
        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador da visita para atualização.']);
                return;
            }

            $payload = get_json_input();
            $updated = $repository->update($id, $payload);
            if ($updated === null) {
                respond_not_found('Visita');
                return;
            }

            json_response($updated);
            return;
        case 'DELETE':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador da visita para exclusão.']);
                return;
            }

            $repository->delete($id);
            json_response(['deleted' => true]);
            return;
        default:
            respond_method_not_allowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            return;
    }
}

function handleContracts(ContractRepository $repository, string $method, ?int $id): void
{
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $contract = $repository->find($id);
                if ($contract === null) {
                    respond_not_found('Contrato');
                    return;
                }

                json_response($contract);
                return;
            }

            $pagination = build_pagination($_GET);
            $filters = [
                'status' => $_GET['status'] ?? null,
                'type' => $_GET['type'] ?? null,
            ];

            json_response($repository->all($filters, $pagination));
            return;
        case 'POST':
            $payload = get_json_input();
            json_response($repository->create($payload), 201);
            return;
        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do contrato para atualização.']);
                return;
            }

            $payload = get_json_input();
            $updated = $repository->update($id, $payload);
            if ($updated === null) {
                respond_not_found('Contrato');
                return;
            }

            json_response($updated);
            return;
        case 'DELETE':
            if ($id === null) {
                respond_validation_error(['id' => 'Informe o identificador do contrato para exclusão.']);
                return;
            }

            $repository->delete($id);
            json_response(['deleted' => true]);
            return;
        default:
            respond_method_not_allowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            return;
    }
}
