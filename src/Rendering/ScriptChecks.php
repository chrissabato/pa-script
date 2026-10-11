<?php

declare(strict_types=1);

namespace App\Rendering;

use App\Repository\GameRosterRepository;
use App\View\Url;

/**
 * Gaps in a game's data that make its script read wrong or incomplete —
 * shown above the script so they're fixed before air, not discovered
 * mid-read. Each check is [message, fix-it URL].
 */
class ScriptChecks
{
    /**
     * @param array<string, mixed> $game
     * @param array<string, mixed> $context from GameContextBuilder
     * @return array<int, array{0: string, 1: string}>
     */
    public static function forGame(array $game, array $context): array
    {
        $gameUrl = Url::to('/games/' . (int) $game['id']);
        $checks = [];

        foreach (['away', 'home'] as $side) {
            $team = $context[$side]['shortName'];
            $rosterUrl = $gameUrl . '/roster/' . $side;
            $starters = $context[$side]['starters'];
            $reserves = $context[$side]['reserves'];
            $battingOrder = $context[$side]['battingOrder'];

            if ($starters === [] && $reserves === [] && $battingOrder === []) {
                $checks[] = [$team . "'s roster is empty — import it.", $rosterUrl];
            } elseif (!empty($game['uses_batting_order'])) {
                $slotTen = !empty($game['uses_flex']) ? 'flex' : 'starting-pitcher';
                $checks = array_merge($checks, self::battingOrderChecks($team, $battingOrder, $rosterUrl, $slotTen));
            } elseif ($starters === []) {
                $checks[] = ['No starters marked for ' . $team . '.', $rosterUrl];
            }

            $headCoach = $context[$side]['headCoach'];
            if ($headCoach === null) {
                $checks[] = ['No coaches for ' . $team . ' — the head-coach line is skipped.', $rosterUrl];
            } elseif ($headCoach['seasonsOrdinal'] === '') {
                $checks[] = [$headCoach['name'] . "'s season count isn't set, so the \"in their Nth season\" part is skipped.", $rosterUrl];
            }

            $heads = array_filter($context[$side]['coaches'], function (array $coach): bool {
                return (bool) preg_match('/^\s*head\b/i', (string) $coach['title']);
            });
            if (count($heads) > 1) {
                $checks[] = [
                    $team . ' has ' . count($heads) . ' coaches titled "Head…"; ' . $headCoach['name']
                        . ' is read as head coach and the rest as assistants.',
                    $rosterUrl,
                ];
            }
        }

        if ($context['officials'] === []) {
            $checks[] = ['No officials yet — the officials line is skipped.', $gameUrl . '#officials'];
        }
        if (($game['time_of_day'] ?? null) === null) {
            $checks[] = ['No game time set — reads "Good afternoon".', $gameUrl];
        }

        return $checks;
    }

    /**
     * Baseball/Softball: every slot 1–9 filled, once each; $slotTen names
     * slot 10 ("flex", "starting-pitcher").
     *
     * @param array<int, array<string, mixed>> $battingOrder
     * @return array<int, array{0: string, 1: string}>
     */
    private static function battingOrderChecks(string $team, array $battingOrder, string $rosterUrl, string $slotTen): array
    {
        if ($battingOrder === []) {
            return [['No batting order set for ' . $team . '.', $rosterUrl]];
        }

        $checks = [];
        $bySlot = [];
        foreach ($battingOrder as $player) {
            $bySlot[(int) $player['batting_order']][] = $player['name'];
        }
        foreach ($bySlot as $slot => $names) {
            if (count($names) > 1) {
                $spot = $slot === GameRosterRepository::FLEX_SLOT ? $slotTen : Spoken::ordinal($slot);
                $checks[] = [
                    PlaceholderRegistry::joinWithAnd($names) . ' share the ' . $spot . ' spot in ' . $team . "'s batting order.",
                    $rosterUrl,
                ];
            }
        }
        $missing = array_values(array_diff(range(1, 9), array_keys($bySlot)));
        if ($missing !== []) {
            $checks[] = [
                $team . "'s batting order has no " . PlaceholderRegistry::joinWithAnd(array_map(function (int $slot): string {
                    return Spoken::ordinal($slot);
                }, $missing)) . ' batter.',
                $rosterUrl,
            ];
        }

        return $checks;
    }
}
