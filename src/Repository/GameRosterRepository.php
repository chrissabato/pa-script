<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\PgType;
use PDO;

/**
 * Per-game rosters (game_roster_snapshots + game_roster_players/coaches) —
 * the only rosters the app keeps. Each game side has its own, edited on
 * that game's roster page.
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

    /**
     * Roster size per side, for the game overview's at-a-glance check that
     * both rosters were imported.
     *
     * @return array<string, array{players: int, starters: int, coaches: int}> keyed by side
     */
    public function countsForGame(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            'select s.side, '
            . '(select count(*) from game_roster_players p where p.snapshot_id = s.id) as players, '
            . '(select count(*) from game_roster_players p where p.snapshot_id = s.id and p.is_starter) as starters, '
            . '(select count(*) from game_roster_coaches c where c.snapshot_id = s.id) as coaches '
            . 'from game_roster_snapshots s where s.game_id = :game_id'
        );
        $stmt->execute(['game_id' => $gameId]);

        $counts = [];
        foreach (['home', 'away'] as $side) {
            $counts[$side] = ['players' => 0, 'starters' => 0, 'coaches' => 0];
        }
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['side']] = [
                'players' => (int) $row['players'],
                'starters' => (int) $row['starters'],
                'coaches' => (int) $row['coaches'],
            ];
        }

        return $counts;
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
     * Creates an empty roster for one side of a new game. It's filled in on
     * the game's roster page — fetched from the team's roster URL, imported
     * with the bookmarklet, or typed in. `program_id` records which team
     * (and so which saved roster URL) the side belongs to.
     */
    public function createSnapshot(int $gameId, int $programId, string $side): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_snapshots (game_id, program_id, side) values (:game_id, :program_id, :side) '
            . 'returning id'
        );
        $stmt->execute(['game_id' => $gameId, 'program_id' => $programId, 'side' => $side]);

        return (int) $stmt->fetchColumn();
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

    public function createCoach(int $snapshotId, string $name, ?string $pronunciation, ?string $title, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_coaches (snapshot_id, name, pronunciation, title, sort_order) '
            . 'values (:snapshot_id, :name, :pronunciation, :title, :sort_order) returning id'
        );
        $stmt->execute([
            'snapshot_id' => $snapshotId,
            'name' => $name,
            'pronunciation' => $pronunciation !== '' ? $pronunciation : null,
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
