<?php

declare(strict_types=1);

namespace App\Roster;

/**
 * Suggests a program's roster-page URL from its school's athletics site
 * and CMS provider (both from the team API), e.g.
 *
 *   sidearm: https://golcathletics.com/sports/womens-volleyball/roster
 *   presto:  https://wubearcats.com/sports/wvball/2026-27/roster
 *
 * Only a suggestion — the roster page shows it for a human to confirm or
 * correct, and a corrected URL is saved on the program.
 */
class RosterUrlGuesser
{
    /**
     * Presto's short sport codes, keyed by sport slug then division slug
     * ('' = divisionless). Also the list of which sports are gendered.
     */
    private const PRESTO_CODES = [
        'soccer' => ['womens' => 'wsoc', 'mens' => 'msoc'],
        'basketball' => ['womens' => 'wbkb', 'mens' => 'mbkb'],
        'volleyball' => ['womens' => 'wvball', 'mens' => 'mvball'],
        'lacrosse' => ['womens' => 'wlax', 'mens' => 'mlax'],
        'baseball' => ['' => 'bsb'],
        'softball' => ['' => 'sball'],
    ];

    /**
     * @param string|null $siteUrl      the school's athletics site (schools.url)
     * @param string|null $cmsProvider  schools.cms_provider: sidearm, presto, presto_old
     * @param string|null $divisionSlug womens/mens, or null for divisionless sports
     * @param string      $onDate       Y-m-d; picks Presto's academic-year season
     */
    public function guess(?string $siteUrl, ?string $cmsProvider, string $sportSlug, ?string $divisionSlug, string $onDate): ?string
    {
        $base = rtrim(trim((string) $siteUrl), '/');
        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return null;
        }

        $provider = strtolower(trim((string) $cmsProvider));
        $divisionSlug = (string) $divisionSlug;

        // A gendered sport with no division (e.g. a game created without
        // one) can't be resolved to a roster — don't guess which team.
        $codes = self::PRESTO_CODES[$sportSlug] ?? null;
        if ($codes !== null && !isset($codes[$divisionSlug])) {
            return null;
        }

        if ($provider === 'sidearm') {
            $slug = $divisionSlug !== '' ? $divisionSlug . '-' . $sportSlug : $sportSlug;

            return $base . '/sports/' . $slug . '/roster';
        }

        if ($provider === 'presto' || $provider === 'presto_old') {
            if ($codes === null) {
                return null;
            }

            return $base . '/sports/' . $codes[$divisionSlug] . '/' . self::season($onDate) . '/roster';
        }

        return null;
    }

    /**
     * Presto URLs pin a season ("/sports/wvball/2025-26/roster"), so a URL
     * saved last year would keep importing last year's roster. Rolls a
     * saved URL's YYYY-YY segment forward to the season for $onDate; any
     * other URL is returned unchanged.
     */
    public function currentSeason(string $url, string $onDate): string
    {
        return (string) preg_replace(
            '#(/sports/[^/]+/)\d{4}-\d{2}(/roster)#',
            '${1}' . self::season($onDate) . '${2}',
            $url
        );
    }

    /** Academic-year season label: July 2026–June 2027 is "2026-27". */
    public static function season(string $onDate): string
    {
        $timestamp = strtotime($onDate);
        $year = (int) date('Y', $timestamp);
        if ((int) date('n', $timestamp) < 7) {
            $year--;
        }

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }
}
