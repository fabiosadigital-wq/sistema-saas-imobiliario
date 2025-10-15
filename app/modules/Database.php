<?php

declare(strict_types=1);

namespace App\Modules;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (($config['driver'] ?? '') !== 'sqlite') {
            throw new RuntimeException('Atualmente somente o driver SQLite é suportado neste ambiente.');
        }

        $databasePath = $config['database'] ?? null;
        if ($databasePath === null) {
            throw new RuntimeException('Caminho do banco de dados SQLite não configurado.');
        }

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $exception) {
            throw new RuntimeException('Não foi possível abrir o banco de dados SQLite: ' . $exception->getMessage(), 0, $exception);
        }

        self::$connection = $pdo;

        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $queries = [
            'CREATE TABLE IF NOT EXISTS properties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT,
                type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "available",
                price REAL NOT NULL,
                condo_fee REAL DEFAULT 0,
                city TEXT,
                state TEXT,
                neighborhood TEXT,
                address TEXT,
                bedrooms INTEGER DEFAULT 0,
                bathrooms INTEGER DEFAULT 0,
                suites INTEGER DEFAULT 0,
                parking_spots INTEGER DEFAULT 0,
                area REAL DEFAULT 0,
                owner_name TEXT,
                owner_email TEXT,
                owner_phone TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                type TEXT NOT NULL DEFAULT "buyer",
                stage TEXT NOT NULL DEFAULT "new",
                preferences TEXT,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS visits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                scheduled_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "scheduled",
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
            )',
            'CREATE TABLE IF NOT EXISTS contracts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                property_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                value REAL NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
            )',
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }
    }
}
