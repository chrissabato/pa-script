<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class CoachRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function findByProgram(int $programId): array
    {
        $stmt = $this->pdo->prepare(
            'select * from coaches where program_id = :program_id order by sort_order, name'
        );
        $stmt->execute(['program_id' => $programId]);

        return $stmt->fetchAll();
    }

    public function create(int $programId, string $name, ?string $title, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'insert into coaches (program_id, name, title, sort_order) '
            . 'values (:program_id, :name, :title, :sort_order) returning id'
        );
        $stmt->execute([
            'program_id' => $programId,
            'name' => $name,
            'title' => $title !== '' ? $title : null,
            'sort_order' => $sortOrder,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, string $name, ?string $title, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'update coaches set name = :name, title = :title, sort_order = :sort_order, '
            . 'updated_at = current_timestamp where id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'title' => $title !== '' ? $title : null,
            'sort_order' => $sortOrder,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from coaches where id = :id');
        $stmt->execute(['id' => $id]);
    }
}
