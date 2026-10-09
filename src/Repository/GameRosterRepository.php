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

    /**
     * Fills a new game side from the team's latest roster this academic year
     * (August–July around `$date`): the same program's most recent other game
     * whose roster has players or coaches. Captains carry over; starters,
     * libero and batting order are per game, so they start cleared.
     *
     * @return int|null the source game's id, or null when nothing was copied
     */
    public function copyFromLatestThisYear(int $snapshotId, int $programId, int $gameId, string $date): ?int
    {
        list($from, $to) = self::academicYear($date);
        $stmt = $this->pdo->prepare(
            'select s.id, s.game_id from game_roster_snapshots s join games g on g.id = s.game_id '
            . 'where s.program_id = :program_id and s.game_id <> :game_id and g.date between :from and :to '
            . 'and (exists (select 1 from game_roster_players p where p.snapshot_id = s.id) '
            . 'or exists (select 1 from game_roster_coaches c where c.snapshot_id = s.id)) '
            . 'order by g.date desc, g.time_of_day desc nulls last, g.id desc limit 1'
        );
        $stmt->execute(['program_id' => $programId, 'game_id' => $gameId, 'from' => $from, 'to' => $to]);
        $source = $stmt->fetch();
        if ($source === false) {
            return null;
        }

        $params = ['to_id' => $snapshotId, 'from_id' => (int) $source['id']];
        $this->pdo->prepare(
            'insert into game_roster_players (snapshot_id, number, name, pronunciation, position, year, hometown, '
            . 'is_captain) select :to_id, number, name, pronunciation, position, year, hometown, is_captain '
            . 'from game_roster_players where snapshot_id = :from_id'
        )->execute($params);
        $this->pdo->prepare(
            'insert into game_roster_coaches (snapshot_id, name, pronunciation, title, pronoun, seasons, sort_order) '
            . 'select :to_id, name, pronunciation, title, pronoun, seasons, sort_order '
            . 'from game_roster_coaches where snapshot_id = :from_id'
        )->execute($params);

        return (int) $source['game_id'];
    }

    /**
     * The academic year (August 1 – July 31) a Y-m-d date falls in.
     *
     * @return array{0: string, 1: string} first and last day, Y-m-d
     */
    public static function academicYear(string $date): array
    {
        $year = (int) substr($date, 0, 4);
        $startYear = (int) substr($date, 5, 2) >= 8 ? $year : $year - 1;

        return [$startYear . '-08-01', ($startYear + 1) . '-07-31'];
    }

    /** @param array<string, mixed> $data */
    public function createPlayer(int $snapshotId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_players (snapshot_id, number, name, pronunciation, position, year, '
            . 'hometown, is_starter, is_captain, is_libero, batting_order) '
            . 'values (:snapshot_id, :number, :name, :pronunciation, :position, :year, :hometown, '
            . ':is_starter, :is_captain, :is_libero, :batting_order) returning id'
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
            . 'is_captain = :is_captain, is_libero = :is_libero, '
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

    public function createCoach(
        int $snapshotId,
        string $name,
        ?string $pronunciation,
        ?string $title,
        int $sortOrder,
        ?string $pronoun = null,
        ?int $seasons = null
    ): int {
        $stmt = $this->pdo->prepare(
            'insert into game_roster_coaches (snapshot_id, name, pronunciation, title, sort_order, pronoun, seasons) '
            . 'values (:snapshot_id, :name, :pronunciation, :title, :sort_order, :pronoun, :seasons) returning id'
        );
        $stmt->execute([
            'snapshot_id' => $snapshotId,
            'name' => $name,
            'pronunciation' => $pronunciation !== '' ? $pronunciation : null,
            'title' => $title !== '' ? $title : null,
            'sort_order' => $sortOrder,
            'pronoun' => $pronoun !== '' ? $pronoun : null,
            'seasons' => $seasons,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Edits a coach row from the roster page. `pronoun` (his/her/their) and
     * `seasons` feed the head-coach line ("in her 4th season").
     */
    public function updateCoach(
        int $id,
        string $name,
        ?string $pronunciation,
        ?string $title,
        ?string $pronoun,
        ?int $seasons,
        int $sortOrder
    ): void {
        $stmt = $this->pdo->prepare(
            'update game_roster_coaches set name = :name, pronunciation = :pronunciation, title = :title, '
            . 'pronoun = :pronoun, seasons = :seasons, sort_order = :sort_order, updated_at = current_timestamp '
            . 'where id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'pronunciation' => $pronunciation !== '' ? $pronunciation : null,
            'title' => $title !== '' ? $title : null,
            'pronoun' => $pronoun !== '' ? $pronoun : null,
            'seasons' => $seasons,
            'sort_order' => $sortOrder,
        ]);
    }

    /** Empties one side's player list, before an import replaces it. */
    public function deletePlayersFor(int $snapshotId): void
    {
        $stmt = $this->pdo->prepare('delete from game_roster_players where snapshot_id = :snapshot_id');
        $stmt->execute(['snapshot_id' => $snapshotId]);
    }

    /** Empties one side's coaching staff, before an import replaces it. */
    public function deleteCoachesFor(int $snapshotId): void
    {
        $stmt = $this->pdo->prepare('delete from game_roster_coaches where snapshot_id = :snapshot_id');
        $stmt->execute(['snapshot_id' => $snapshotId]);
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
            'is_captain' => PgType::boolToString(!empty($data['is_captain'])),
            'is_libero' => PgType::boolToString(!empty($data['is_libero'])),
            'batting_order' => ($data['batting_order'] ?? '') !== '' ? (int) $data['batting_order'] : null,
        ];
    }
}
