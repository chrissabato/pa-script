<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * How roster abbreviations are read aloud — the app's version of the
 * source sheets' FORMATPOS / FORMATCLASS / FORMATSTATE functions
 * ("GK" -> "goalie", "Fy." -> "first year", "Issaquah, Wash." ->
 * "Issaquah, Washington"). Per-sport position words are data here, not
 * template forks (positions live in Positions). Anything unrecognized is read lowercased as-is, like
 * the sheets do.
 */
class Spoken
{
    /** Class year (lowercase, no dots/spaces) -> spoken form. */
    private const YEARS = [
        'fy' => 'first year', 'firstyear' => 'first year', 'fr' => 'first year', 'freshman' => 'first year',
        'so' => 'sophomore', 'soph' => 'sophomore', 'sophomore' => 'sophomore', 'sophmore' => 'sophomore',
        'jr' => 'junior', 'junior' => 'junior',
        'sr' => 'senior', 'senior' => 'senior',
        'gr' => 'graduate student', 'grad' => 'graduate student', 'graduate' => 'graduate student',
        '5th' => 'fifth-year senior', '5thyear' => 'fifth-year senior',
    ];

    /** AP-style and postal state abbreviations -> full names. */
    private const STATES = [
        'ala' => 'Alabama', 'al' => 'Alabama', 'alaska' => 'Alaska', 'ak' => 'Alaska',
        'ariz' => 'Arizona', 'az' => 'Arizona', 'ark' => 'Arkansas', 'ar' => 'Arkansas',
        'calif' => 'California', 'cal' => 'California', 'ca' => 'California',
        'colo' => 'Colorado', 'co' => 'Colorado', 'conn' => 'Connecticut', 'ct' => 'Connecticut',
        'del' => 'Delaware', 'de' => 'Delaware', 'fla' => 'Florida', 'fl' => 'Florida',
        'ga' => 'Georgia', 'hawaii' => 'Hawaii', 'hi' => 'Hawaii', 'idaho' => 'Idaho', 'id' => 'Idaho',
        'ill' => 'Illinois', 'il' => 'Illinois', 'ind' => 'Indiana', 'in' => 'Indiana', 'iowa' => 'Iowa', 'ia' => 'Iowa',
        'kan' => 'Kansas', 'kans' => 'Kansas', 'ks' => 'Kansas', 'ky' => 'Kentucky', 'la' => 'Louisiana',
        'maine' => 'Maine', 'me' => 'Maine', 'md' => 'Maryland', 'mass' => 'Massachusetts', 'ma' => 'Massachusetts',
        'mich' => 'Michigan', 'mi' => 'Michigan', 'minn' => 'Minnesota', 'mn' => 'Minnesota',
        'miss' => 'Mississippi', 'ms' => 'Mississippi', 'mo' => 'Missouri', 'mont' => 'Montana', 'mt' => 'Montana',
        'neb' => 'Nebraska', 'nebr' => 'Nebraska', 'ne' => 'Nebraska', 'nev' => 'Nevada', 'nv' => 'Nevada',
        'nh' => 'New Hampshire', 'nj' => 'New Jersey', 'nm' => 'New Mexico', 'ny' => 'New York',
        'nc' => 'North Carolina', 'nd' => 'North Dakota', 'ohio' => 'Ohio', 'oh' => 'Ohio',
        'okla' => 'Oklahoma', 'ok' => 'Oklahoma', 'ore' => 'Oregon', 'or' => 'Oregon',
        'pa' => 'Pennsylvania', 'penn' => 'Pennsylvania', 'ri' => 'Rhode Island', 'sc' => 'South Carolina',
        'sd' => 'South Dakota', 'tenn' => 'Tennessee', 'tn' => 'Tennessee', 'texas' => 'Texas', 'tex' => 'Texas', 'tx' => 'Texas',
        'utah' => 'Utah', 'ut' => 'Utah', 'vt' => 'Vermont', 'va' => 'Virginia',
        'wash' => 'Washington', 'wa' => 'Washington', 'wva' => 'West Virginia', 'wv' => 'West Virginia',
        'wis' => 'Wisconsin', 'wisc' => 'Wisconsin', 'wi' => 'Wisconsin', 'wyo' => 'Wyoming', 'wy' => 'Wyoming',
        'dc' => 'D.C.',
    ];

    /**
     * A listed position read aloud, from the per-sport library
     * (Positions). Combined positions ("F/M", "Outside Hitter/Defensive
     * Specialist OH/DS") are read as their first part.
     */
    public static function position(?string $position, string $sportSlug): string
    {
        $first = Positions::parse($position, $sportSlug)[0] ?? null;
        if ($first === null) {
            return '';
        }
        $entry = $first['abbr'] !== null ? Positions::find($first['abbr'], $sportSlug) : null;

        return $entry !== null ? $entry['spoken'] : strtolower($first['text']);
    }

    public static function year(?string $year): string
    {
        $year = trim((string) $year);
        if ($year === '') {
            return '';
        }
        $key = strtolower(preg_replace('/[\s.\-]/', '', $year));

        return self::YEARS[$key] ?? strtolower($year);
    }

    /**
     * Expands a trailing state abbreviation ("Issaquah, Wash." ->
     * "Issaquah, Washington"); hometowns with a full state or a country
     * ("Montevideo, Uruguay") are left alone.
     */
    public static function hometown(?string $hometown): string
    {
        $hometown = trim((string) $hometown);
        $comma = strrpos($hometown, ',');
        if ($comma === false) {
            return $hometown;
        }
        $state = trim(substr($hometown, $comma + 1));
        $key = strtolower(str_replace(['.', ' '], '', $state));
        if (!isset(self::STATES[$key])) {
            return $hometown;
        }

        return rtrim(substr($hometown, 0, $comma)) . ', ' . self::STATES[$key];
    }

    public const NAME_DISPLAYS = [
        'name' => 'Name',
        'pronunciation' => 'Pronunciation',
        'both' => 'Name + Pronunciation',
    ];

    /** A new game's name_display (also the games column's default). */
    public const DEFAULT_NAME_DISPLAY = 'both';

    /**
     * A person's name as the script shows it (a game's name_display):
     * 'name' — "Siera Edwards"; 'pronunciation' — "SEE-era ED-werds", or the
     * name when there's no guide; 'both' — "Siera Edwards (SEE-era
     * ED-werds)", just the name when there's no guide or it's the same.
     */
    public static function name(array $person, string $display = self::DEFAULT_NAME_DISPLAY): string
    {
        $name = trim((string) ($person['name'] ?? ''));
        $pronunciation = trim((string) ($person['pronunciation'] ?? ''));

        if ($display === 'name' || $pronunciation === '') {
            return $name;
        }
        if ($display === 'both') {
            return strcasecmp($pronunciation, $name) === 0 ? $name : $name . ' (' . $pronunciation . ')';
        }

        return $pronunciation;
    }

    /** 1 -> "1st", 17 -> "17th", 22 -> "22nd", 11 -> "11th". */
    public static function ordinal(int $n): string
    {
        $suffix = 'th';
        if (!in_array($n % 100, [11, 12, 13], true)) {
            $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';
        }

        return $n . $suffix;
    }

    /** "morning" before noon, "afternoon" before 5pm, else "evening"; null when no time is set. */
    public static function partOfDay(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }
        $hour = (int) substr($time, 0, 2);
        if ($hour < 12) {
            return 'morning';
        }

        return $hour < 17 ? 'afternoon' : 'evening';
    }
}
