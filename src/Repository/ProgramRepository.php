<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\PgType;
use PDO;

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
            sports.name as sport_name,
            sports.slug as sport_slug,
            divisions.name as division_name,
            divisions.slug as division_slug
        from programs
        join schools on schools.id = programs.school_id
        join sports on sports.id = programs.sport_id
        left join divisions on divisions.id = programs.division_id
        SQL;

    /** @return array<int, array<string, mixed>> */
    public function allWillamette(): array
    {
        $stmt = $this->pdo->query(self::SELECT . ' where programs.is_willamette = true order by sports.name, divisions.name');

        return $stmt->fetchAll();
    }

    /**
     * All programs (including opponents) — used by the import review
     * destination picker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(self::SELECT . ' order by schools.name, sports.name, divisions.name');

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' where programs.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findWillametteProgram(int $sportId, ?int $divisionId): ?array
    {
        $sql = self::SELECT . ' where programs.sport_id = :sport_id and programs.is_willamette = true';
        $params = ['sport_id' => $sportId];

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

    public function create(int $schoolId, int $sportId, ?int $divisionId, bool $isWillamette): int
    {
        $stmt = $this->pdo->prepare(
            'insert into programs (school_id, sport_id, division_id, is_willamette) '
            . 'values (:school_id, :sport_id, :division_id, :is_willamette) returning id'
        );
        $stmt->execute([
            'school_id' => $schoolId,
            'sport_id' => $sportId,
            'division_id' => $divisionId,
            'is_willamette' => PgType::boolToString($isWillamette),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findOrCreate(int $schoolId, int $sportId, ?int $divisionId, bool $isWillamette): array
    {
        $existing = $this->findBySchoolSportDivision($schoolId, $sportId, $divisionId);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->create($schoolId, $sportId, $divisionId, $isWillamette);

        return $this->find($id);
    }
}
