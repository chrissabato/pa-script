<?php

declare(strict_types=1);

namespace App;

/**
 * Per-install settings from `.env`, so another school can run the app
 * without code changes. Defaults are Willamette's, the original install.
 */
final class Site
{
    public const DEFAULT_NAME = 'Willamette Athletics';
    public const DEFAULT_HOME_TEAM_SLUG = 'willamette-university';
    public const DEFAULT_TEAMS_API_URL = 'https://athletics.willamette.edu/api/teams';

    /** Shown in the page header before "PA Script" (SITE_NAME). */
    public static function name(): string
    {
        return self::env('SITE_NAME', self::DEFAULT_NAME);
    }

    /**
     * The Team Data API slug of the school running this install
     * (HOME_TEAM_SLUG): preselected as the home team, and its programs are
     * flagged as ours.
     */
    public static function homeTeamSlug(): string
    {
        return self::env('HOME_TEAM_SLUG', self::DEFAULT_HOME_TEAM_SLUG);
    }

    /** Base URL of the Team Data API the schools cache syncs from (TEAMS_API_URL). */
    public static function teamsApiUrl(): string
    {
        return rtrim(self::env('TEAMS_API_URL', self::DEFAULT_TEAMS_API_URL), '/');
    }

    private static function env(string $key, string $default): string
    {
        $value = trim((string) ($_ENV[$key] ?? ''));

        return $value === '' ? $default : $value;
    }
}
