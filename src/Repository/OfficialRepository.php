<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class OfficialRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function findByGame(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            'select * from officials where game_id = :game_id order by sort_order, name'
        );
        $stmt->execute(['game_id' => $gameId]);

        return $stmt->fetchAll();
    }

    public function create(int $gameId, string $name, ?string $role, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'insert into officials (game_id, name, role, sort_order) '
            . 'values (:game_id, :name, :role, :sort_order) returning id'
        );
        $stmt->execute([
            'game_id' => $gameId,
            'name' => $name,
            'role' => $role !== '' ? $role : null,
            'sort_order' => $sortOrder,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteByGame(int $gameId): void
    {
        $stmt = $this->pdo->prepare('delete from officials where game_id = :game_id');
        $stmt->execute(['game_id' => $gameId]);
    }
}
