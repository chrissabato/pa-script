<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class ScriptTemplateRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('select * from script_templates where id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Resolves the template for a game: the neutral-site variant (division-
     * agnostic) when the game is at a neutral site, otherwise the home
     * variant for the given division.
     */
    public function findForGame(int $sportId, ?int $divisionId, bool $isNeutralSite): ?array
    {
        // A template with a division (Women's Soccer) wins for that
        // division; one without (Volleyball, neutral-site Soccer) covers
        // every division of the sport, including games created with one.
        $stmt = $this->pdo->prepare(
            'select * from script_templates where sport_id = :sport_id and site_variant = :site_variant '
            . 'and (division_id = :division_id or division_id is null) '
            . 'order by division_id is null limit 1'
        );
        $stmt->execute([
            'sport_id' => $sportId,
            'site_variant' => $isNeutralSite ? 'neutral' : 'home',
            'division_id' => $divisionId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
