<?php

declare(strict_types=1);

namespace App\Modules;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class ContractRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(array $filters, array $pagination): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $conditions[] = 'c.type = :type';
            $params[':type'] = $filters['type'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contracts c {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT c.*, p.title AS property_title, cl.name AS client_name
            FROM contracts c
            INNER JOIN properties p ON p.id = c.property_id
            INNER JOIN clients cl ON cl.id = c.client_id
            {$where}
            ORDER BY c.start_date DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'meta' => [
                'total' => $total,
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT c.*, p.title AS property_title, cl.name AS client_name
            FROM contracts c
            INNER JOIN properties p ON p.id = c.property_id
            INNER JOIN clients cl ON cl.id = c.client_id
            WHERE c.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        require_fields($data, ['property_id', 'client_id', 'type', 'start_date', 'value']);

        if (!$this->recordExists('properties', (int) $data['property_id'])) {
            respond_validation_error(['property_id' => 'Imóvel informado não existe.']);
        }

        if (!$this->recordExists('clients', (int) $data['client_id'])) {
            respond_validation_error(['client_id' => 'Cliente informado não existe.']);
        }

        $payload = [
            'property_id' => (int) $data['property_id'],
            'client_id' => (int) $data['client_id'],
            'type' => sanitize_string($data['type'] ?? null) ?? 'sale',
            'start_date' => sanitize_string($data['start_date'] ?? null),
            'end_date' => sanitize_string($data['end_date'] ?? null),
            'value' => sanitize_float($data['value'] ?? null) ?? 0.0,
            'status' => sanitize_string($data['status'] ?? null) ?? 'draft',
            'notes' => sanitize_string($data['notes'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = array_keys($payload);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);
        $sql = sprintf('INSERT INTO contracts (%s) VALUES (%s)', implode(', ', $columns), implode(', ', $placeholders));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($payload)));

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        if (!$this->find($id)) {
            return null;
        }

        $payload = [
            'type' => sanitize_string($data['type'] ?? null),
            'start_date' => sanitize_string($data['start_date'] ?? null),
            'end_date' => sanitize_string($data['end_date'] ?? null),
            'value' => sanitize_float($data['value'] ?? null),
            'status' => sanitize_string($data['status'] ?? null),
            'notes' => sanitize_string($data['notes'] ?? null),
            'updated_at' => now(),
        ];

        $payload = array_filter($payload, static fn ($value) => $value !== null);

        if (!$payload) {
            return $this->find($id);
        }

        $assignments = [];
        $params = [];

        foreach ($payload as $column => $value) {
            $assignments[] = sprintf('%s = :%s', $column, $column);
            $params[':' . $column] = $value;
        }

        $params[':id'] = $id;

        $sql = sprintf('UPDATE contracts SET %s WHERE id = :id', implode(', ', $assignments));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function expiringSoon(int $days): array
    {
        $now = new DateTimeImmutable('now');
        $limit = $now->add(new DateInterval('P' . max(1, $days) . 'D'));

        $sql = "SELECT c.*, p.title AS property_title, cl.name AS client_name
            FROM contracts c
            INNER JOIN properties p ON p.id = c.property_id
            INNER JOIN clients cl ON cl.id = c.client_id
            WHERE c.end_date IS NOT NULL AND c.end_date BETWEEN :start AND :end
            ORDER BY c.end_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $now->format(DateTimeInterface::ATOM),
            ':end' => $limit->format(DateTimeInterface::ATOM),
        ]);

        return $stmt->fetchAll();
    }

    private function recordExists(string $table, int $id): bool
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT 1 FROM %s WHERE id = :id', $table));
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }
}
