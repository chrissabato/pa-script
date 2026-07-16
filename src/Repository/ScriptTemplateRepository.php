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
        if ($isNeutralSite) {
            $stmt = $this->pdo->prepare(
                "select * from script_templates where sport_id = :sport_id and site_variant = 'neutral' "
                . 'and division_id is null'
            );
            $stmt->execute(['sport_id' => $sportId]);
        } else {
            $stmt = $this->pdo->prepare(
                "select * from script_templates where sport_id = :sport_id and site_variant = 'home' "
                . 'and division_id = :division_id'
            );
            $stmt->execute(['sport_id' => $sportId, 'division_id' => $divisionId]);
        }

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
