<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\PgType;
use PDO;

/**
 * Programs are a team in a sport (school × sport × division). Games point
 * at one per side, and a program remembers its team's roster-page URL so
 * the next game against that team can fetch from it again. Programs hold
 * no roster themselves — rosters live on each game.
 */
class ProgramRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private const SELECT = <<<'SQL'
        select
            programs.*,
            schools.name as school_name,
            schools.mascot as school_mascot,
            schools.short_name as school_short_name,
            schools.url as school_url,
            schools.cms_provider as school_cms_provider,
            sports.name as sport_name,
            sports.slug as sport_slug,
            divisions.name as division_name,
            divisions.slug as division_slug
        from programs
        join schools on schools.id = programs.school_id
        join sports on sports.id = programs.sport_id
        left join divisions on divisions.id = programs.division_id
SQL;

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' where programs.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findBySchoolSportDivision(int $schoolId, int $sportId, ?int $divisionId): ?array
    {
        $sql = self::SELECT . ' where programs.school_id = :school_id and programs.sport_id = :sport_id';
        $params = ['school_id' => $schoolId, 'sport_id' => $sportId];

        if ($divisionId === null) {
            $sql .= ' and programs.division_id is null';
        } else {
            $sql .= ' and programs.division_id = :division_id';
            $params['division_id'] = $divisionId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, int $sportId, ?int $divisionId, bool $isOwnSchool): int
    {
        $stmt = $this->pdo->prepare(
            'insert into programs (school_id, sport_id, division_id, is_own_school) '
            . 'values (:school_id, :sport_id, :division_id, :is_own_school) returning id'
        );
        $stmt->execute([
            'school_id' => $schoolId,
            'sport_id' => $sportId,
            'division_id' => $divisionId,
            'is_own_school' => PgType::boolToString($isOwnSchool),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findOrCreate(int $schoolId, int $sportId, ?int $divisionId, bool $isOwnSchool): array
    {
        $existing = $this->findBySchoolSportDivision($schoolId, $sportId, $divisionId);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->create($schoolId, $sportId, $divisionId, $isOwnSchool);

        return $this->find($id);
    }

    public function updateRosterUrl(int $id, ?string $rosterUrl): void
    {
        $stmt = $this->pdo->prepare(
            'update programs set roster_url = :roster_url, updated_at = current_timestamp where id = :id'
        );
        $stmt->execute(['id' => $id, 'roster_url' => $rosterUrl]);
    }


}
