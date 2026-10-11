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

    private const SELECT_WITH_NAMES = 'select script_templates.*, sports.name as sport_name, sports.slug as sport_slug, '
        . 'divisions.name as division_name, divisions.slug as division_slug from script_templates '
        . 'join sports on sports.id = script_templates.sport_id '
        . 'left join divisions on divisions.id = script_templates.division_id';

    /**
     * Every template with its sport/division names, for the editor's list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithNames(): array
    {
        return $this->pdo->query(
            self::SELECT_WITH_NAMES . ' order by sports.name, divisions.name nulls first, script_templates.site_variant'
        )->fetchAll();
    }

    public function findWithNames(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_WITH_NAMES . ' where script_templates.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Saves new content blocks. $edited marks a save from the editor
     * (ScriptTemplatesSeeder then leaves the row alone); false is a reset to
     * the default wording, which the seeder keeps up to date again.
     *
     * @param array<int, array<string, mixed>> $blocks
     */
    public function updateBlocks(int $id, array $blocks, bool $edited): void
    {
        $stmt = $this->pdo->prepare(
            'update script_templates set content_blocks = :content_blocks, version = version + 1, '
            . 'updated_at = current_timestamp, edited_at = ' . ($edited ? 'current_timestamp' : 'null')
            . ' where id = :id'
        );
        $stmt->execute(['content_blocks' => json_encode($blocks), 'id' => $id]);
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
