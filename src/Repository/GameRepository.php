<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class GameRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private const SELECT = <<<'SQL'
        select
            games.*,
            sports.name as sport_name,
            sports.slug as sport_slug,
            home.school_id as home_school_id,
            away.school_id as away_school_id,
            home.division_id as division_id,
            home_division.name as division_name,
            home_division.slug as division_slug,
            home_school.name as home_school_name,
            home_school.mascot as home_mascot,
            away_school.name as away_school_name,
            away_school.mascot as away_mascot
        from games
        join sports on sports.id = games.sport_id
        left join programs home on home.id = games.home_program_id
        left join programs away on away.id = games.away_program_id
        left join schools home_school on home_school.id = home.school_id
        left join schools away_school on away_school.id = away.school_id
        left join divisions home_division on home_division.id = home.division_id
        SQL;

    /** @return array<int, array<string, mixed>> */
    public function upcoming(): array
    {
        $stmt = $this->pdo->query(self::SELECT . ' order by games.date, games.time_of_day');

        return $stmt->fetchAll();
    }

    /**
     * Games on or after a date (Y-m-d, in the app's local timezone).
     *
     * @return array<int, array<string, mixed>>
     */
    public function onOrAfter(string $date): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' where games.date >= :date order by games.date, games.time_of_day'
        );
        $stmt->execute(['date' => $date]);

        return $stmt->fetchAll();
    }

    /** Roster snapshots and officials go with it (ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from games where id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' where games.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'insert into games (sport_id, date, time_of_day, home_program_id, away_program_id, '
            . 'is_neutral_site, neutral_site_note, venue, script_template_id, anthem_note, notes, status) '
            . 'values (:sport_id, :date, :time_of_day, :home_program_id, :away_program_id, '
            . ':is_neutral_site, :neutral_site_note, :venue, :script_template_id, :anthem_note, :notes, :status) '
            . 'returning id'
        );
        $stmt->execute($data);

        return (int) $stmt->fetchColumn();
    }
}
