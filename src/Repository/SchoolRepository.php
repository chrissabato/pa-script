<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Read-only access to `schools`, the local cache of the athletics Team Data
 * API. Rows are written only by App\Teams\TeamApiSync.
 */
class SchoolRepository
{
    public const WILLAMETTE_SLUG = 'willamette-university';

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('select * from schools order by name');

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('select * from schools where id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('select * from schools where slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** When the cache was last refreshed from the API (UTC), or null if never. */
    public function lastSyncedAt(): ?string
    {
        $value = $this->pdo->query('select max(synced_at) from schools')->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }
}
