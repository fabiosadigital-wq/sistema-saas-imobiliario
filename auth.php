<?php

declare(strict_types=1);

function ensure_authenticated(array $config): void
{
    $expectedKey = $config['auth']['api_key'] ?? '';

    if ($expectedKey === '') {
        return; // Autenticação desativada
    }

    $providedKey = null;

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        if (stripos($header, 'Bearer ') === 0) {
            $providedKey = substr($header, 7);
        } elseif (stripos($header, 'Token ') === 0) {
            $providedKey = substr($header, 6);
        } else {
            $providedKey = $header;
        }
    } elseif (!empty($_GET['api_key'])) {
        $providedKey = (string) $_GET['api_key'];
    }

    if ($providedKey === null || !hash_equals($expectedKey, $providedKey)) {
        json_response([
            'error' => 'unauthorized',
            'message' => 'Credenciais inválidas. Informe um API Key válido.',
        ], 401);
        exit;
    }
}
