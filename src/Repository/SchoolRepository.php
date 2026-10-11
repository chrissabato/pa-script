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
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('select * from schools order by name, variant nulls first');

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

    /** The name as shown in pickers: "Cal Lutheran University (Women)" for a variant entry. */
    public static function label(array $school): string
    {
        $variant = trim((string) ($school['variant'] ?? ''));

        return (string) $school['name'] . ($variant !== '' ? ' (' . $variant . ')' : '');
    }

    /**
     * For a school with men's/women's entries (different mascots), the
     * entry matching the game's division — so a Women's game against "Cal
     * Lutheran (Men)" announces the Regals, not the Kingsmen. Anything else
     * (no variant, no division, no matching twin) comes back unchanged.
     *
     * @param array<string, mixed> $school
     * @param array<string, mixed>|null $division
     * @return array<string, mixed>
     */
    public function forDivision(array $school, ?array $division): array
    {
        if (trim((string) ($school['variant'] ?? '')) === '' || $division === null) {
            return $school;
        }

        $stmt = $this->pdo->prepare('select * from schools where name = :name and variant is not null and id <> :id');
        $stmt->execute(['name' => $school['name'], 'id' => $school['id']]);
        foreach ($stmt->fetchAll() as $twin) {
            if (self::sameGender((string) $twin['variant'], (string) $division['name'])) {
                return $twin;
            }
        }

        return $school;
    }

    /** "Women" vs "Women's" / "womens", "Men" vs "Men's". */
    private static function sameGender(string $variant, string $division): bool
    {
        $normalize = function (string $value): string {
            return (string) preg_replace('/s$/', '', preg_replace('/[^a-z]/', '', strtolower($value)));
        };

        return $normalize($variant) !== '' && $normalize($variant) === $normalize($division);
    }

    /** When the cache was last refreshed from the API (UTC), or null if never. */
    public function lastSyncedAt(): ?string
    {
        $value = $this->pdo->query('select max(synced_at) from schools')->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }
}
