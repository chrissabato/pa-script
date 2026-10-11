<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class SportRepository
{
    /**
     * The teams Willamette fields, as the New Game form offers them:
     * "sport/division" (sport and division slugs; no division for
     * Baseball/Softball) => label. Parsed back by parseTeam().
     */
    public const TEAMS = [
        'baseball' => 'Baseball',
        'softball' => 'Softball',
        'soccer/mens' => "Men's Soccer",
        'soccer/womens' => "Women's Soccer",
        'basketball/mens' => "Men's Basketball",
        'basketball/womens' => "Women's Basketball",
        'volleyball/womens' => "Women's Volleyball",
        'lacrosse/womens' => "Women's Lacrosse",
    ];

    /**
     * A TEAMS key split into its sport and division slugs, or null if it
     * isn't one.
     *
     * @return array{0: string, 1: string|null}|null
     */
    public static function parseTeam(string $team): ?array
    {
        if (!array_key_exists($team, self::TEAMS)) {
            return null;
        }
        $parts = explode('/', $team, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('select * from sports order by name');

        return $stmt->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('select * from sports where slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** The venue a home game's script reads when the game has none typed in; null clears it. */
    public function updateDefaultVenue(int $id, ?string $venue): void
    {
        $stmt = $this->pdo->prepare('update sports set default_venue = :venue where id = :id');
        $stmt->execute(['venue' => $venue, 'id' => $id]);
    }
}
