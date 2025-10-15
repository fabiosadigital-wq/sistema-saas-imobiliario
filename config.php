<?php

return [
    'app' => [
        'name' => 'SaaS Imobiliário',
        'timezone' => 'America/Sao_Paulo',
    ],
    'database' => [
        'driver' => 'sqlite',
        'database' => __DIR__ . '/storage/database.sqlite',
    ],
    'auth' => [
        'api_key' => getenv('SAAS_IMOBILIARIO_API_KEY') ?: 'local-dev-key',
    ],
    'dashboard' => [
        'upcoming_visit_days' => 7,
        'expiring_contract_days' => 30,
    ],
];
