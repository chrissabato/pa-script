<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Bookmarklet scrape staging (import_batches). Nothing here ever writes to
 * the real players/coaches or game_roster_* tables directly — commits into
 * those happen from the review screen via PlayerRepository/CoachRepository/
 * GameRosterRepository, per PLAN.md's "staging first" design.
 */
class ImportBatchRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @param array<string, mixed> $rawPayload */
    public function create(?string $sourceUrl, array $rawPayload): int
    {
        $stmt = $this->pdo->prepare(
            'insert into import_batches (source_url, raw_payload, status) '
            . 'values (:source_url, :raw_payload, :status) returning id'
        );
        $stmt->execute([
            'source_url' => $sourceUrl,
            'raw_payload' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('select * from import_batches where id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('update import_batches set status = :status where id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}
