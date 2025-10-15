<?php

declare(strict_types=1);

require __DIR__ . '/../../functions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Modules\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/../modules/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use App\Modules\ContractRepository;
use App\Modules\Database;
use App\Modules\VisitRepository;

$config = require __DIR__ . '/../../config.php';
$pdo = Database::connection($config['database']);
Database::migrate($pdo);

$visitRepository = new VisitRepository($pdo);
$contractRepository = new ContractRepository($pdo);

$upcoming = $visitRepository->upcoming((int) ($config['dashboard']['upcoming_visit_days'] ?? 7));
$expiring = $contractRepository->expiringSoon((int) ($config['dashboard']['expiring_contract_days'] ?? 30));

$output = [
    'generated_at' => now(),
    'upcoming_visits' => $upcoming,
    'expiring_contracts' => $expiring,
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
