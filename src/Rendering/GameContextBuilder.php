<?php

declare(strict_types=1);

namespace App\Rendering;

use App\Repository\GameRosterRepository;
use App\Repository\OfficialRepository;

/**
 * Assembles the render context array (see PlaceholderRegistry/TemplateRenderer)
 * for a single game from its roster snapshots and officials.
 */
class GameContextBuilder
{
    /** @var GameRosterRepository */
    private $gameRosterRepository;

    /** @var OfficialRepository */
    private $officialRepository;

    public function __construct(GameRosterRepository $gameRosterRepository, OfficialRepository $officialRepository)
    {
        $this->gameRosterRepository = $gameRosterRepository;
        $this->officialRepository = $officialRepository;
    }

    /** @param array<string, mixed> $game */
    public function build(array $game): array
    {
        return [
            'home' => $this->sideContext((int) $game['id'], 'home', $game['home_school_name'], $game['home_mascot']),
            'away' => $this->sideContext((int) $game['id'], 'away', $game['away_school_name'], $game['away_mascot']),
            'officials' => $this->officialRepository->findByGame((int) $game['id']),
            'meta' => [
                'anthemNote' => $game['anthem_note'] ?? '',
                'venue' => $game['venue'] ?? '',
                'date' => $game['date'] ?? '',
                'neutralSiteNote' => $game['neutral_site_note'] ?? '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function sideContext(int $gameId, string $side, ?string $schoolName, ?string $mascot): array
    {
        $snapshot = $this->gameRosterRepository->findSnapshot($gameId, $side);

        $players = $snapshot !== null ? $this->gameRosterRepository->playersFor((int) $snapshot['id']) : [];
        $coaches = $snapshot !== null ? $this->gameRosterRepository->coachesFor((int) $snapshot['id']) : [];

        $starters = array_values(array_filter($players, function (array $p): bool {
            return (bool) $p['is_starter'] && $p['batting_order'] === null;
        }));
        $reserves = array_values(array_filter($players, function (array $p): bool {
            return !$p['is_starter'] && $p['batting_order'] === null;
        }));
        $battingOrder = array_values(array_filter($players, function (array $p): bool {
            return $p['batting_order'] !== null;
        }));
        usort($battingOrder, function (array $a, array $b): int {
            return $a['batting_order'] <=> $b['batting_order'];
        });

        return [
            'schoolName' => $schoolName ?? '',
            'mascot' => $mascot ?? '',
            'coaches' => $coaches,
            'starters' => $starters,
            'reserves' => $reserves,
            'battingOrder' => $battingOrder,
        ];
    }
}
