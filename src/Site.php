<?php

declare(strict_types=1);

namespace App;

/**
 * Per-install settings from `.env`, so another school can run the app
 * without code changes. SITE_NAME defaults to Willamette's (the original
 * install); HOME_TEAM_SLUG and TEAMS_API_URL have no default.
 */
final class Site
{
    public const DEFAULT_NAME = 'Willamette Athletics';

    /** Shown in the page header before "PA Script" (SITE_NAME). */
    public static function name(): string
    {
        return self::env('SITE_NAME', self::DEFAULT_NAME);
    }

    /**
     * The Team Data API slug of the school running this install
     * (HOME_TEAM_SLUG): preselected as the home team, and its programs are
     * flagged as ours. Unset = empty: no team preselected.
     */
    public static function homeTeamSlug(): string
    {
        return self::env('HOME_TEAM_SLUG', '');
    }

    /**
     * Base URL of the Team Data API the schools cache syncs from
     * (TEAMS_API_URL, e.g. https://athletics.willamette.edu/api/teams; run
     * your own with https://github.com/chrissabato/opponent-api). Unset =
     * empty, and team sync refuses to run.
     */
    public static function teamsApiUrl(): string
    {
        return rtrim(self::env('TEAMS_API_URL', ''), '/');
    }

    private static function env(string $key, string $default): string
    {
        $value = trim((string) ($_ENV[$key] ?? ''));

        return $value === '' ? $default : $value;
    }
}
