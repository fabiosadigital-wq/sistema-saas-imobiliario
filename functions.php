<?php

declare(strict_types=1);

function json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response([
            'error' => 'invalid_json',
            'message' => 'O corpo da requisição não é um JSON válido.',
            'details' => json_last_error_msg(),
        ], 400);
        exit;
    }

    return $decoded;
}

function require_fields(array $payload, array $required): array
{
    $missing = [];
    foreach ($required as $field) {
        if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
            $missing[] = $field;
        }
    }

    if ($missing) {
        json_response([
            'error' => 'validation_error',
            'message' => 'Campos obrigatórios ausentes.',
            'fields' => $missing,
        ], 422);
        exit;
    }

    return $payload;
}

function sanitize_string(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function sanitize_float($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float) $value;
}

function build_pagination(array $query): array
{
    $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
    $perPage = isset($query['per_page']) ? max(1, min(100, (int) $query['per_page'])) : 20;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'limit' => $perPage,
    ];
}

function respond_not_found(string $resource = 'recurso'): void
{
    json_response([
        'error' => 'not_found',
        'message' => sprintf('%s não encontrado.', ucfirst($resource)),
    ], 404);
}

function respond_method_not_allowed(array $allowed): void
{
    header('Allow: ' . implode(', ', $allowed));
    json_response([
        'error' => 'method_not_allowed',
        'message' => 'Método HTTP não permitido para esta rota.',
        'allowed' => $allowed,
    ], 405);
}

function respond_validation_error(array $errors): void
{
    json_response([
        'error' => 'validation_error',
        'message' => 'Os dados enviados não são válidos.',
        'details' => $errors,
    ], 422);
}

function now(): string
{
    return (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
}

function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
