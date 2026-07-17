<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

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

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('select * from schools where name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $name, ?string $mascot, ?string $shortName): int
    {
        $stmt = $this->pdo->prepare(
            'insert into schools (name, mascot, short_name) values (:name, :mascot, :short_name) returning id'
        );
        $stmt->execute(['name' => $name, 'mascot' => $mascot, 'short_name' => $shortName]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, string $name, ?string $mascot, ?string $shortName): void
    {
        $stmt = $this->pdo->prepare(
            'update schools set name = :name, mascot = :mascot, short_name = :short_name, '
            . 'updated_at = current_timestamp where id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'mascot' => $mascot, 'short_name' => $shortName]);
    }

    public function findOrCreateByName(string $name, ?string $mascot = null): array
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->create($name, $mascot, null);

        return $this->find($id);
    }
}
