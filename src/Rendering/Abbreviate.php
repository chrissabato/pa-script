<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * Short forms for tight layouts (the printed roster): class years and
 * hometown states, whatever form a scraped roster used ("Junior", "jr",
 * "Jr." -> "Jr."; "Portland, Oregon", "Portland, OR", "Portland, Ore." ->
 * "Portland, Ore."). States use AP style, matching most college rosters;
 * AP leaves eight short state names spelled out. The spoken (long) forms
 * live in Spoken.
 */
class Abbreviate
{
    /** Spoken::year() output -> short form. "Fy." is Willamette's first-year abbreviation. */
    private const YEARS = [
        'first year' => 'Fy.',
        'sophomore' => 'So.',
        'junior' => 'Jr.',
        'senior' => 'Sr.',
        'graduate student' => 'Gr.',
        'fifth-year senior' => '5th',
    ];

    /** Full state name -> AP abbreviation (absent = AP spells it out). */
    private const AP_STATES = [
        'Alabama' => 'Ala.', 'Arizona' => 'Ariz.', 'Arkansas' => 'Ark.', 'California' => 'Calif.',
        'Colorado' => 'Colo.', 'Connecticut' => 'Conn.', 'Delaware' => 'Del.', 'Florida' => 'Fla.',
        'Georgia' => 'Ga.', 'Illinois' => 'Ill.', 'Indiana' => 'Ind.', 'Kansas' => 'Kan.',
        'Kentucky' => 'Ky.', 'Louisiana' => 'La.', 'Maryland' => 'Md.', 'Massachusetts' => 'Mass.',
        'Michigan' => 'Mich.', 'Minnesota' => 'Minn.', 'Mississippi' => 'Miss.', 'Missouri' => 'Mo.',
        'Montana' => 'Mont.', 'Nebraska' => 'Neb.', 'Nevada' => 'Nev.', 'New Hampshire' => 'N.H.',
        'New Jersey' => 'N.J.', 'New Mexico' => 'N.M.', 'New York' => 'N.Y.', 'North Carolina' => 'N.C.',
        'North Dakota' => 'N.D.', 'Oklahoma' => 'Okla.', 'Oregon' => 'Ore.', 'Pennsylvania' => 'Pa.',
        'Rhode Island' => 'R.I.', 'South Carolina' => 'S.C.', 'South Dakota' => 'S.D.', 'Tennessee' => 'Tenn.',
        'Vermont' => 'Vt.', 'Virginia' => 'Va.', 'Washington' => 'Wash.', 'West Virginia' => 'W.Va.',
        'Wisconsin' => 'Wis.', 'Wyoming' => 'Wyo.',
    ];

    /** "Junior" / "jr" / "Jr." -> "Jr."; "Freshman" / "Fr." / "First Year" -> "Fy."; unknown kept as-is. */
    public static function year(?string $year): string
    {
        $year = trim((string) $year);
        if ($year === '') {
            return '';
        }
        $spoken = Spoken::year($year);

        return self::YEARS[$spoken] ?? $year;
    }

    /**
     * "Portland, Oregon" / "Portland, OR" / "Portland, Ore." -> "Portland,
     * Ore."; AP's spelled-out states ("Hilo, Hawaii") and countries
     * ("Montevideo, Uruguay") are left alone.
     */
    public static function hometown(?string $hometown): string
    {
        $hometown = trim((string) $hometown);
        $full = Spoken::hometown($hometown);
        $comma = strrpos($full, ',');
        if ($comma === false) {
            return $hometown;
        }
        $state = trim(substr($full, $comma + 1));

        return isset(self::AP_STATES[$state])
            ? rtrim(substr($full, 0, $comma)) . ', ' . self::AP_STATES[$state]
            : $full;
    }
}
