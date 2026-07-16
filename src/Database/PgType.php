<?php

declare(strict_types=1);

namespace App\Database;

/**
 * PDOStatement::execute($array) binds every value as a plain string. With
 * emulated prepares off (see Connection::fromEnv), Postgres's native
 * protocol then rejects PHP's (string) cast of false ('') as a boolean
 * literal — only true's cast ('1') happens to parse. Booleans bound this
 * way must be converted explicitly.
 */
class PgType
{
    public static function boolToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
