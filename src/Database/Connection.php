<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class Connection
{
    public static function fromEnv(): PDO
    {
        $db = Config::fromEnv($_ENV);

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['sslmode']
        );

        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Production shares a database with other athletics apps; this
        // app's tables live in their own schema (see Config).
        if ($db['schema'] !== '') {
            $pdo->exec('SET search_path TO ' . self::quoteIdentifier($db['schema']));
        }

        return $pdo;
    }

    public static function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
