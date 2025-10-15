<?php

declare(strict_types=1);

namespace App\Modules;

use PDO;

final class ClientRepository
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

        if (!empty($filters['type'])) {
            $conditions[] = 'type = :type';
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['stage'])) {
            $conditions[] = 'stage = :stage';
            $params[':stage'] = $filters['stage'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(LOWER(name) LIKE LOWER(:search) OR LOWER(email) LIKE LOWER(:search))';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM clients {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM clients {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        require_fields($data, ['name']);

        $payload = [
            'name' => sanitize_string($data['name'] ?? null),
            'email' => sanitize_string($data['email'] ?? null),
            'phone' => sanitize_string($data['phone'] ?? null),
            'type' => sanitize_string($data['type'] ?? null) ?? 'buyer',
            'stage' => sanitize_string($data['stage'] ?? null) ?? 'new',
            'preferences' => sanitize_string($data['preferences'] ?? null),
            'notes' => sanitize_string($data['notes'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = array_keys($payload);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);

        $sql = sprintf('INSERT INTO clients (%s) VALUES (%s)', implode(', ', $columns), implode(', ', $placeholders));
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
            'name' => sanitize_string($data['name'] ?? null),
            'email' => sanitize_string($data['email'] ?? null),
            'phone' => sanitize_string($data['phone'] ?? null),
            'type' => sanitize_string($data['type'] ?? null),
            'stage' => sanitize_string($data['stage'] ?? null),
            'preferences' => sanitize_string($data['preferences'] ?? null),
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

        $sql = sprintf('UPDATE clients SET %s WHERE id = :id', implode(', ', $assignments));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM clients WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function stageSummary(): array
    {
        $stmt = $this->pdo->query('SELECT stage, COUNT(*) as total FROM clients GROUP BY stage');
        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[$row['stage']] = (int) $row['total'];
        }

        return $summary;
    }
}
