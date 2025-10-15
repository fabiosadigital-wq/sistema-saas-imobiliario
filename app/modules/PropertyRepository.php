<?php

declare(strict_types=1);

namespace App\Modules;

use PDO;

final class PropertyRepository
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
            $conditions[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['city'])) {
            $conditions[] = 'LOWER(city) = LOWER(:city)';
            $params[':city'] = $filters['city'];
        }

        if (!empty($filters['min_price'])) {
            $conditions[] = 'price >= :min_price';
            $params[':min_price'] = (float) $filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $conditions[] = 'price <= :max_price';
            $params[':max_price'] = (float) $filters['max_price'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM properties {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $orderBy = 'created_at DESC';
        if (!empty($filters['order'])) {
            $orderBy = match ($filters['order']) {
                'price_asc' => 'price ASC',
                'price_desc' => 'price DESC',
                'newest' => 'created_at DESC',
                'oldest' => 'created_at ASC',
                default => 'created_at DESC',
            };
        }

        $sql = "SELECT * FROM properties {$where} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
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
        $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        $payload = $this->normalizePayload($data);
        $payload['code'] = $this->generateCode();
        $payload['created_at'] = $payload['updated_at'] = now();

        $columns = array_keys($payload);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO properties (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($payload)));

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        if (!$this->find($id)) {
            return null;
        }

        $payload = $this->normalizePayload($data, false);
        $payload['updated_at'] = now();

        $assignments = [];
        $params = [];
        foreach ($payload as $column => $value) {
            $assignments[] = sprintf('%s = :%s', $column, $column);
            $params[':' . $column] = $value;
        }

        if (!$assignments) {
            return $this->find($id);
        }

        $params[':id'] = $id;

        $sql = sprintf('UPDATE properties SET %s WHERE id = :id', implode(', ', $assignments));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM properties WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function statusSummary(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) as total FROM properties GROUP BY status');
        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    private function normalizePayload(array $data, bool $isCreate = true): array
    {
        $payload = [
            'title' => sanitize_string($data['title'] ?? null),
            'description' => sanitize_string($data['description'] ?? null),
            'type' => sanitize_string($data['type'] ?? null) ?? 'residential',
            'status' => sanitize_string($data['status'] ?? null) ?? 'available',
            'price' => sanitize_float($data['price'] ?? null) ?? 0.0,
            'condo_fee' => sanitize_float($data['condo_fee'] ?? null) ?? 0.0,
            'city' => sanitize_string($data['city'] ?? null),
            'state' => sanitize_string($data['state'] ?? null),
            'neighborhood' => sanitize_string($data['neighborhood'] ?? null),
            'address' => sanitize_string($data['address'] ?? null),
            'bedrooms' => isset($data['bedrooms']) ? (int) $data['bedrooms'] : 0,
            'bathrooms' => isset($data['bathrooms']) ? (int) $data['bathrooms'] : 0,
            'suites' => isset($data['suites']) ? (int) $data['suites'] : 0,
            'parking_spots' => isset($data['parking_spots']) ? (int) $data['parking_spots'] : 0,
            'area' => sanitize_float($data['area'] ?? null) ?? 0.0,
            'owner_name' => sanitize_string($data['owner_name'] ?? null),
            'owner_email' => sanitize_string($data['owner_email'] ?? null),
            'owner_phone' => sanitize_string($data['owner_phone'] ?? null),
        ];

        if ($isCreate) {
            require_fields($data, ['title', 'type', 'price']);
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null
        );
    }

    private function generateCode(): string
    {
        return 'IMV-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
