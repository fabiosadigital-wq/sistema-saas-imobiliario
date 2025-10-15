<?php

declare(strict_types=1);

namespace App\Modules;

use PDO;

final class DashboardService
{
    private PDO $pdo;
    private PropertyRepository $properties;
    private ClientRepository $clients;
    private VisitRepository $visits;
    private ContractRepository $contracts;

    public function __construct(
        PDO $pdo,
        PropertyRepository $properties,
        ClientRepository $clients,
        VisitRepository $visits,
        ContractRepository $contracts
    ) {
        $this->pdo = $pdo;
        $this->properties = $properties;
        $this->clients = $clients;
        $this->visits = $visits;
        $this->contracts = $contracts;
    }

    public function metrics(int $visitDays, int $contractDays): array
    {
        return [
            'totals' => [
                'properties' => $this->count('properties'),
                'clients' => $this->count('clients'),
                'visits' => $this->count('visits'),
                'contracts' => $this->count('contracts'),
            ],
            'properties_by_status' => $this->properties->statusSummary(),
            'clients_by_stage' => $this->clients->stageSummary(),
            'upcoming_visits' => $this->visits->upcoming($visitDays),
            'expiring_contracts' => $this->contracts->expiringSoon($contractDays),
            'revenue' => $this->revenueSummary(),
        ];
    }

    private function count(string $table): int
    {
        $stmt = $this->pdo->query(sprintf('SELECT COUNT(*) FROM %s', $table));
        return (int) $stmt->fetchColumn();
    }

    private function revenueSummary(): array
    {
        $sql = "SELECT strftime('%Y-%m', start_date) AS reference, SUM(value) as total
            FROM contracts
            WHERE status IN ('active', 'completed')
            GROUP BY strftime('%Y-%m', start_date)
            ORDER BY reference DESC
            LIMIT 6";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $row) => [
            'reference' => $row['reference'],
            'total' => (float) $row['total'],
        ], $rows);
    }
}
