<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * The per-sport position library: each position's standard abbreviation,
 * full name, the word the script reads, and the other ways rosters list
 * it. Rosters write positions every which way — "OH", "Outside Hitter",
 * or Sidearm's long and short forms run together ("Outside
 * Hitter/Defensive Specialist OH/DS") — and this resolves any of them to
 * the abbreviation (Abbreviate::position) or the spoken word
 * (Spoken::position).
 */
class Positions
{
    /**
     * Sport slug -> abbreviation -> [name, spoken word if not the
     * lowercased name, other spellings]. The abbreviation and name are
     * always recognized; aliases are matched lowercase without dots.
     */
    private const SPORTS = [
        'soccer' => [
            'GK' => ['name' => 'Goalkeeper', 'spoken' => 'goalie', 'aliases' => ['g', 'goalie', 'keeper']],
            'D' => ['name' => 'Defender', 'spoken' => 'defense', 'aliases' => ['b', 'def', 'back', 'defense']],
            'M' => ['name' => 'Midfielder', 'spoken' => 'midfield', 'aliases' => ['mf', 'mid', 'midfield']],
            'F' => ['name' => 'Forward', 'aliases' => ['fw', 'fwd']],
            // Specific spots, read as the sheet's general words.
            'CB' => ['name' => 'Center Back', 'spoken' => 'defense'],
            'FB' => ['name' => 'Fullback', 'spoken' => 'defense', 'aliases' => ['full back']],
            'LB' => ['name' => 'Left Back', 'spoken' => 'defense'],
            'RB' => ['name' => 'Right Back', 'spoken' => 'defense'],
            'CM' => ['name' => 'Central Midfielder', 'spoken' => 'midfield'],
            'DM' => ['name' => 'Defensive Midfielder', 'spoken' => 'midfield'],
            'CDM' => ['name' => 'Central Defensive Midfielder', 'spoken' => 'midfield'],
            'DCM' => ['name' => 'Defensive Central Midfielder', 'spoken' => 'midfield'],
            'AM' => ['name' => 'Attacking Midfielder', 'spoken' => 'midfield'],
            'CAM' => ['name' => 'Central Attacking Midfielder', 'spoken' => 'midfield'],
            'ACM' => ['name' => 'Attacking Central Midfielder', 'spoken' => 'midfield'],
            'W' => ['name' => 'Winger', 'spoken' => 'forward', 'aliases' => ['wing']],
            'ST' => ['name' => 'Striker', 'spoken' => 'forward'],
        ],
        'basketball' => [
            'G' => ['name' => 'Guard'],
            'PG' => ['name' => 'Point Guard', 'spoken' => 'guard'],
            'SG' => ['name' => 'Shooting Guard', 'spoken' => 'guard'],
            'F' => ['name' => 'Forward'],
            'SF' => ['name' => 'Small Forward', 'spoken' => 'forward'],
            'PF' => ['name' => 'Power Forward', 'spoken' => 'forward'],
            'W' => ['name' => 'Wing'],
            'C' => ['name' => 'Center'],
            'P' => ['name' => 'Post'],
        ],
        // As the Lacrosse sheet reads them; "M/D" reads "midfield".
        'lacrosse' => [
            'G' => ['name' => 'Goalie', 'aliases' => ['gk', 'goalkeeper']],
            'D' => ['name' => 'Defense', 'aliases' => ['def', 'defender', 'defenseman']],
            'M' => ['name' => 'Midfield', 'aliases' => ['mf', 'mid', 'midfielder']],
            'A' => ['name' => 'Attack', 'aliases' => ['att', 'attacker']],
            'LSM' => ['name' => 'Long Stick Midfielder', 'aliases' => ['long stick middie']],
            'SSDM' => ['name' => 'Short Stick Defensive Midfielder'],
            'FO' => ['name' => 'Faceoff Specialist', 'aliases' => ['fogo', 'faceoff']],
        ],
        // The roster position is the fallback when a lineup slot has no
        // "Playing" position for the game (GameContextBuilder).
        'baseball' => [
            'P' => ['name' => 'Pitcher'],
            'RHP' => ['name' => 'Right-Handed Pitcher', 'spoken' => 'pitcher'],
            'LHP' => ['name' => 'Left-Handed Pitcher', 'spoken' => 'pitcher'],
            'C' => ['name' => 'Catcher'],
            '1B' => ['name' => 'First Base', 'aliases' => ['first baseman']],
            '2B' => ['name' => 'Second Base', 'aliases' => ['second baseman']],
            '3B' => ['name' => 'Third Base', 'aliases' => ['third baseman']],
            'SS' => ['name' => 'Shortstop'],
            'LF' => ['name' => 'Left Field', 'aliases' => ['left fielder']],
            'CF' => ['name' => 'Center Field', 'aliases' => ['center fielder']],
            'RF' => ['name' => 'Right Field', 'aliases' => ['right fielder']],
            'OF' => ['name' => 'Outfield', 'aliases' => ['outfielder']],
            'INF' => ['name' => 'Infield', 'aliases' => ['if', 'infielder']],
            'UT' => ['name' => 'Utility', 'aliases' => ['util', 'ut']],
            'DH' => ['name' => 'Designated Hitter'],
        ],
        'softball' => [
            'P' => ['name' => 'Pitcher'],
            'RHP' => ['name' => 'Right-Handed Pitcher', 'spoken' => 'pitcher'],
            'LHP' => ['name' => 'Left-Handed Pitcher', 'spoken' => 'pitcher'],
            'C' => ['name' => 'Catcher'],
            '1B' => ['name' => 'First Base', 'aliases' => ['first baseman']],
            '2B' => ['name' => 'Second Base', 'aliases' => ['second baseman']],
            '3B' => ['name' => 'Third Base', 'aliases' => ['third baseman']],
            'SS' => ['name' => 'Shortstop'],
            'LF' => ['name' => 'Left Field', 'aliases' => ['left fielder']],
            'CF' => ['name' => 'Center Field', 'aliases' => ['center fielder']],
            'RF' => ['name' => 'Right Field', 'aliases' => ['right fielder']],
            'OF' => ['name' => 'Outfield', 'aliases' => ['outfielder']],
            'INF' => ['name' => 'Infield', 'aliases' => ['if', 'infielder']],
            'UT' => ['name' => 'Utility', 'aliases' => ['util']],
            'DP' => ['name' => 'Designated Player', 'aliases' => ['dh', 'designated hitter']],
        ],
        // As the Volleyball sheet reads them ("OH" is "outside", not
        // "outside hitter"). The match's libero is normally ticked on the
        // roster page (is_libero); a listed position of "L" reads the same.
        'volleyball' => [
            'S' => ['name' => 'Setter'],
            'OH' => ['name' => 'Outside Hitter', 'spoken' => 'outside', 'aliases' => ['outside']],
            'MB' => ['name' => 'Middle Blocker', 'aliases' => ['m', 'middle']],
            'MH' => ['name' => 'Middle Hitter'],
            'OPP' => ['name' => 'Opposite', 'aliases' => ['op', 'opposite hitter']],
            'RS' => ['name' => 'Right Side Hitter', 'spoken' => 'right side', 'aliases' => ['rsh', 'right side']],
            'DS' => ['name' => 'Defensive Specialist'],
            'L' => ['name' => 'Libero', 'aliases' => ['lib']],
        ],
    ];

    /**
     * Splits a listed position into its parts, each resolved to its
     * abbreviation (null when the library doesn't know it). "Outside
     * Hitter/Defensive Specialist OH/DS" -> [OH, DS]; "Setter S" -> [S].
     *
     * @return list<array{abbr: ?string, text: string}>
     */
    public static function parse(?string $position, string $sportSlug): array
    {
        $position = trim(preg_replace('/\s+/u', ' ', (string) $position) ?? '');
        if ($position === '') {
            return [];
        }
        // Long form followed by its abbreviation (Sidearm prints both):
        // trust the trailing abbreviation when every part of it is known.
        if (preg_match('#^(.*\S)\s+([A-Z0-9]+(?:\s*/\s*[A-Z0-9]+)*)$#', $position, $m)) {
            $short = self::parseParts($m[2], $sportSlug);
            if (!in_array(null, array_column($short, 'abbr'), true)) {
                return $short;
            }
        }

        return self::parseParts($position, $sportSlug);
    }

    /** A position's library entry by abbreviation ("OH"), or null. */
    public static function find(string $abbr, string $sportSlug): ?array
    {
        $entry = self::SPORTS[$sportSlug][$abbr] ?? null;
        if ($entry === null) {
            return null;
        }

        return [
            'abbr' => $abbr,
            'name' => $entry['name'],
            'spoken' => $entry['spoken'] ?? strtolower($entry['name']),
        ];
    }

    /** @return list<array{abbr: ?string, text: string}> */
    private static function parseParts(string $position, string $sportSlug): array
    {
        $parts = [];
        foreach (preg_split('#\s*[/,&]\s*#', $position) ?: [] as $text) {
            $text = trim($text);
            if ($text !== '') {
                $parts[] = ['abbr' => self::lookup($text, $sportSlug), 'text' => $text];
            }
        }

        return $parts;
    }

    private static function lookup(string $text, string $sportSlug): ?string
    {
        $key = self::key($text);
        foreach (self::SPORTS[$sportSlug] ?? [] as $abbr => $entry) {
            $names = array_merge([(string) $abbr, $entry['name']], $entry['aliases'] ?? []);
            foreach ($names as $name) {
                if (self::key($name) === $key) {
                    return (string) $abbr;
                }
            }
        }

        return null;
    }

    /** "Right-Handed Pitcher" / "right handed  pitcher." -> "right handed pitcher". */
    private static function key(string $text): string
    {
        return trim(preg_replace('/[\s\-]+/u', ' ', strtolower(str_replace('.', '', $text))) ?? '');
    }
}
