<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\PgType;
use PDO;

/**
 * Manages the per-game roster snapshots (game_roster_snapshots +
 * game_roster_players/coaches). Snapshots are copies: editing them never
 * writes back to the persistent players/coaches tables, per PLAN.md.
 */
class GameRosterRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findSnapshot(int $gameId, string $side): ?array
    {
        $stmt = $this->pdo->prepare(
            'select * from game_roster_snapshots where game_id = :game_id and side = :side'
        );
        $stmt->execute(['game_id' => $gameId, 'side' => $side]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function playersFor(int $snapshotId): array
    {
        // Same numeric jersey-number ordering as PlayerRepository::findByProgram.
        $stmt = $this->pdo->prepare(
            "select * from game_roster_players where snapshot_id = :snapshot_id "
            . "order by nullif(regexp_replace(number, '\\D', '', 'g'), '')::int nulls last, number, name"
        );
        $stmt->execute(['snapshot_id' => $snapshotId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function coachesFor(int $snapshotId): array
    {
        $stmt = $this->pdo->prepare(
            'select * from game_roster_coaches where snapshot_id = :snapshot_id order by sort_order, name'
        );
        $stmt->execute(['snapshot_id' => $snapshotId]);

        return $stmt->fetchAll();
    }

    /**
     * Creates a snapshot for a side, seeding it from a program's current
     * persistent roster (if any) — used at game-creation time. An opponent
     * with no persistent roster yet still gets an empty snapshot to fill in
     * later via the snapshot editor or the bookmarklet import.
     */
    public function createSnapshotFromProgram(int $gameId, ?int $programId, string $side): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_snapshots (game_id, program_id, side) values (:game_id, :program_id, :side) '
            . 'returning id'
        );
        $stmt->execute(['game_id' => $gameId, 'program_id' => $programId, 'side' => $side]);
        $snapshotId = (int) $stmt->fetchColumn();

        if ($programId !== null) {
            $this->copyPlayers($snapshotId, $programId);
            $this->copyCoaches($snapshotId, $programId);
        }

        return $snapshotId;
    }

    private function copyPlayers(int $snapshotId, int $programId): void
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_players '
            . '(snapshot_id, source_player_id, number, name, pronunciation, position, year, hometown, '
            . 'is_starter, batting_order) '
            . 'select :snapshot_id, id, number, name, pronunciation, position, year, hometown, '
            . 'is_starter, batting_order '
            . 'from players where program_id = :program_id and is_active = true'
        );
        $stmt->execute(['snapshot_id' => $snapshotId, 'program_id' => $programId]);
    }

    private function copyCoaches(int $snapshotId, int $programId): void
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_coaches (snapshot_id, source_coach_id, name, title, sort_order) '
            . 'select :snapshot_id, id, name, title, sort_order from coaches where program_id = :program_id'
        );
        $stmt->execute(['snapshot_id' => $snapshotId, 'program_id' => $programId]);
    }

    /** @param array<string, mixed> $data */
    public function createPlayer(int $snapshotId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_players (snapshot_id, number, name, pronunciation, position, year, '
            . 'hometown, is_starter, batting_order) '
            . 'values (:snapshot_id, :number, :name, :pronunciation, :position, :year, :hometown, '
            . ':is_starter, :batting_order) returning id'
        );
        $stmt->execute(array_merge(['snapshot_id' => $snapshotId], $this->normalizePlayer($data)));

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function updatePlayer(int $id, array $data): void
    {
        // The roster editor doesn't post batting_order (hidden until the
        // baseball/softball designated-player design lands), so only touch
        // it when a caller actually sends it.
        $params = array_merge(['id' => $id], $this->normalizePlayer($data));
        $battingOrderSet = 'batting_order = :batting_order, ';
        if (!array_key_exists('batting_order', $data)) {
            unset($params['batting_order']);
            $battingOrderSet = '';
        }
        $stmt = $this->pdo->prepare(
            'update game_roster_players set number = :number, name = :name, pronunciation = :pronunciation, '
            . 'position = :position, year = :year, hometown = :hometown, is_starter = :is_starter, '
            . $battingOrderSet . 'updated_at = current_timestamp '
            . 'where id = :id'
        );
        $stmt->execute($params);
    }

    public function deletePlayer(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from game_roster_players where id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function createCoach(int $snapshotId, string $name, ?string $title, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_coaches (snapshot_id, name, title, sort_order) '
            . 'values (:snapshot_id, :name, :title, :sort_order) returning id'
        );
        $stmt->execute([
            'snapshot_id' => $snapshotId,
            'name' => $name,
            'title' => $title !== '' ? $title : null,
            'sort_order' => $sortOrder,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteCoach(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from game_roster_coaches where id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePlayer(array $data): array
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
        ];
    }
}
