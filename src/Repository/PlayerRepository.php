<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\PgType;
use PDO;

class PlayerRepository
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
        // Jersey numbers are strings ("00", "4", "15"), so compare numerically
        // on their digits; unnumbered players go last.
        $stmt = $this->pdo->prepare(
            "select * from players where program_id = :program_id "
            . "order by nullif(regexp_replace(number, '\\D', '', 'g'), '')::int nulls last, number, name"
        );
        $stmt->execute(['program_id' => $programId]);

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(int $programId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'insert into players (program_id, number, name, pronunciation, position, year, hometown, '
            . 'is_starter, batting_order, is_active) '
            . 'values (:program_id, :number, :name, :pronunciation, :position, :year, :hometown, '
            . ':is_starter, :batting_order, :is_active) returning id'
        );
        $stmt->execute(array_merge(['program_id' => $programId], $this->normalize($data)));

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        // The roster editor doesn't post batting_order (hidden until the
        // baseball/softball designated-player design lands), so only touch
        // it when a caller actually sends it.
        $params = array_merge(['id' => $id], $this->normalize($data));
        $battingOrderSet = 'batting_order = :batting_order, ';
        if (!array_key_exists('batting_order', $data)) {
            unset($params['batting_order']);
            $battingOrderSet = '';
        }
        $stmt = $this->pdo->prepare(
            'update players set number = :number, name = :name, pronunciation = :pronunciation, '
            . 'position = :position, year = :year, hometown = :hometown, is_starter = :is_starter, '
            . $battingOrderSet . 'is_active = :is_active, '
            . 'updated_at = current_timestamp '
            . 'where id = :id'
        );
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from players where id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'number' => $data['number'] !== '' ? $data['number'] : null,
            'name' => $data['name'],
            'pronunciation' => $data['pronunciation'] !== '' ? $data['pronunciation'] : null,
            'position' => $data['position'] !== '' ? $data['position'] : null,
            'year' => $data['year'] !== '' ? $data['year'] : null,
            'hometown' => $data['hometown'] !== '' ? $data['hometown'] : null,
            'is_starter' => PgType::boolToString(!empty($data['is_starter'])),
            'batting_order' => ($data['batting_order'] ?? '') !== '' ? (int) $data['batting_order'] : null,
            'is_active' => PgType::boolToString(!empty($data['is_active'])),
        ];
    }
}
