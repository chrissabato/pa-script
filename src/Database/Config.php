<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

/**
 * Database settings, shared by the app (Connection) and Phinx.
 *
 * On the production server the credentials live in the JSON file every
 * athletics app there uses (DB_CREDS_FILE, e.g.
 * /www/athletics.willamette.edu/creds.pgsql.json — keys DB_HOST, DB_PORT,
 * DB_NAME, DB_USER, DB_PASS, optional DB_SSLMODE), pointing at a database
 * other apps share; DB_SCHEMA keeps this app's tables in their own schema.
 * Without DB_CREDS_FILE the same keys are read from .env (dev).
 */
class Config
{
    /**
     * @param array<string, mixed> $env
     * @return array{host: string, port: string, name: string, user: string, pass: string, sslmode: string, schema: string}
     */
    public static function fromEnv(array $env): array
    {
        $source = $env;
        $file = trim((string) ($env['DB_CREDS_FILE'] ?? ''));
        if ($file !== '') {
            if (!is_readable($file)) {
                throw new RuntimeException('Cannot read DB_CREDS_FILE: ' . $file);
            }
            $source = json_decode((string) file_get_contents($file), true);
            if (!is_array($source)) {
                throw new RuntimeException('DB_CREDS_FILE is not valid JSON: ' . $file);
            }
        }

        return [
            'host' => (string) ($source['DB_HOST'] ?? '127.0.0.1'),
            'port' => (string) ($source['DB_PORT'] ?? '5432'),
            'name' => (string) ($source['DB_NAME'] ?? ''),
            'user' => (string) ($source['DB_USER'] ?? ''),
            'pass' => (string) ($source['DB_PASS'] ?? ''),
            'sslmode' => (string) ($source['DB_SSLMODE'] ?? 'prefer'),
            'schema' => trim((string) ($env['DB_SCHEMA'] ?? '')),
        ];
    }
}
