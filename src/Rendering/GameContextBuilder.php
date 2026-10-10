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
        $rosters = [];
        foreach (['home', 'away'] as $side) {
            $snapshot = $this->gameRosterRepository->findSnapshot((int) $game['id'], $side);
            $rosters[$side] = [
                'players' => $snapshot !== null ? $this->gameRosterRepository->playersFor((int) $snapshot['id']) : [],
                'coaches' => $snapshot !== null ? $this->gameRosterRepository->coachesFor((int) $snapshot['id']) : [],
            ];
        }

        return self::assemble($game, $rosters, $this->officialRepository->findByGame((int) $game['id']));
    }

    /**
     * The render context from already-loaded rows — pure, so tests can
     * render a template against fixture data without a database.
     *
     * @param array<string, mixed> $game a GameRepository row
     * @param array<string, array{players: array, coaches: array}> $rosters keyed by side
     * @param array<int, array<string, mixed>> $officials
     * @return array<string, mixed>
     */
    public static function assemble(array $game, array $rosters, array $officials): array
    {
        $partOfDay = Spoken::partOfDay($game['time_of_day'] ?? null);
        $sportSlug = (string) ($game['sport_slug'] ?? '');
        $nameDisplay = (string) ($game['name_display'] ?? Spoken::DEFAULT_NAME_DISPLAY);

        return [
            'home' => self::sideContext($game, 'home', $sportSlug, $nameDisplay, $rosters['home']['players'], $rosters['home']['coaches']),
            'away' => self::sideContext($game, 'away', $sportSlug, $nameDisplay, $rosters['away']['players'], $rosters['away']['coaches']),
            'officials' => $officials,
            'meta' => [
                'anthemNote' => $game['anthem_note'] ?? '',
                'venue' => $game['venue'] ?? '',
                'date' => $game['date'] ?? '',
                'neutralSiteNote' => $game['neutral_site_note'] ?? '',
                // "Good morning" / "afternoon" / "evening"; templates fall
                // back to "afternoon" wording when no time is set.
                'partOfDay' => $partOfDay ?? 'afternoon',
                'isEvening' => $partOfDay === 'evening',
                'isConference' => (bool) ($game['is_conference'] ?? false),
                // Games predating the has_anthem column (or test fixtures) have one.
                'hasAnthem' => (bool) ($game['has_anthem'] ?? true),
                'specialAnnouncement' => trim((string) ($game['special_announcement'] ?? '')),
                'closingAnnouncement' => trim((string) ($game['closing_announcement'] ?? '')),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $game
     * @param array<int, array<string, mixed>> $players
     * @param array<int, array<string, mixed>> $coaches
     * @return array<string, mixed>
     */
    private static function sideContext(
        array $game,
        string $side,
        string $sportSlug,
        string $nameDisplay,
        array $players,
        array $coaches
    ): array
    {
        // Spoken forms for the lineup lines ("A captain, at goalie, a senior
        // from Portland, Oregon, number 1, SEE-era ED-werds").
        $players = array_map(function (array $p) use ($sportSlug, $nameDisplay): array {
            $p['spokenName'] = Spoken::name($p, $nameDisplay);
            // The game's libero is introduced as "libero" whatever their
            // listed position (DS, OH, ...).
            $p['spokenPosition'] = !empty($p['is_libero'])
                ? 'libero'
                : Spoken::position($p['position'] ?? null, $sportSlug);
            $p['spokenYear'] = Spoken::year($p['year'] ?? null);
            $p['spokenHometown'] = Spoken::hometown($p['hometown'] ?? null);

            return $p;
        }, $players);

        // Head coach: the first coach whose title starts with "Head" (not
        // just contains it — "Assistant Coach / Head Men's Soccer Coach" is
        // an assistant here), else the first listed. Everyone else on the
        // staff is read as an assistant.
        $headCoach = null;
        foreach ($coaches as $coach) {
            if (preg_match('/^\s*head\b/i', (string) $coach['title'])) {
                $headCoach = $coach;
                break;
            }
        }
        if ($headCoach === null && $coaches !== []) {
            $headCoach = $coaches[0];
        }
        $assistants = array_values(array_filter($coaches, function (array $c) use ($headCoach): bool {
            return $headCoach === null || (int) $c['id'] !== (int) $headCoach['id'];
        }));
        if ($headCoach !== null) {
            $headCoach['spokenName'] = Spoken::name($headCoach, $nameDisplay);
            $headCoach['seasonsOrdinal'] = $headCoach['seasons'] !== null && (int) $headCoach['seasons'] > 0
                ? Spoken::ordinal((int) $headCoach['seasons'])
                : '';
        }

        $schoolName = (string) ($game[$side . '_school_name'] ?? '');
        $shortName = trim((string) ($game[$side . '_short_name'] ?? ''));

        $starters = self::liberoLast(array_values(array_filter($players, function (array $p): bool {
            return (bool) $p['is_starter'] && $p['batting_order'] === null;
        })));
        $reserves = self::liberoLast(array_values(array_filter($players, function (array $p): bool {
            return !$p['is_starter'] && $p['batting_order'] === null;
        })));
        $battingOrder = array_values(array_filter($players, function (array $p): bool {
            return $p['batting_order'] !== null;
        }));
        usort($battingOrder, function (array $a, array $b): int {
            return $a['batting_order'] <=> $b['batting_order'];
        });

        $startingPitcher = null;
        foreach ($players as $p) {
            if ((bool) $p['is_starter'] && self::isPitcher($p['position'])) {
                $startingPitcher = $p;
                break;
            }
        }

        return [
            'schoolName' => $schoolName,
            'shortName' => $shortName !== '' ? $shortName : $schoolName,
            'mascot' => (string) ($game[$side . '_mascot'] ?? ''),
            'coaches' => $coaches,
            'headCoach' => $headCoach,
            'assistantCoaches' => PlaceholderRegistry::joinWithAnd(array_map(function (array $c) use ($nameDisplay): string {
                return Spoken::name($c, $nameDisplay);
            }, $assistants)),
            'starters' => $starters,
            'reserves' => $reserves,
            'battingOrder' => $battingOrder,
            'startingPitcher' => $startingPitcher,
        ];
    }

    /**
     * Volleyball introduces the libero last in each group (the sheet's
     * ORDER BY on its libero column) — whether ticked as Libero or listed
     * with position "L"; a no-op for every other sport.
     *
     * @param array<int, array<string, mixed>> $players
     * @return array<int, array<string, mixed>>
     */
    private static function liberoLast(array $players): array
    {
        $isLibero = function (array $p): bool {
            return ($p['spokenPosition'] ?? '') === 'libero';
        };

        return array_merge(
            array_values(array_filter($players, function (array $p) use ($isLibero): bool {
                return !$isLibero($p);
            })),
            array_values(array_filter($players, $isLibero))
        );
    }

    /**
     * Baseball/Softball announce the starting pitcher as its own callout,
     * separate from the batting-order loop (which may or may not include
     * them depending on DH use) — identified by position text rather than a
     * dedicated column, so no schema change is needed for other sports.
     */
    private static function isPitcher(?string $position): bool
    {
        return $position !== null && stripos($position, 'pitcher') !== false;
    }
}
