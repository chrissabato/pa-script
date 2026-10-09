<?php

declare(strict_types=1);

namespace App\View;

/**
 * The URL prefix the app is served under ('' at a domain root, '/pa' at
 * athletics.willamette.edu/pa). Every root-relative link, redirect and
 * fetch goes through it, so the app runs unchanged at either.
 */
class Url
{
    /** @var string */
    private static $base = '';

    /**
     * APP_BASE_PATH when set, else the directory of the front controller
     * as the web server sees it (SCRIPT_NAME '/pa/index.php' → '/pa').
     */
    public static function detect(array $env, array $server): string
    {
        if (isset($env['APP_BASE_PATH'])) {
            $base = (string) $env['APP_BASE_PATH'];
        } else {
            // Only trust SCRIPT_NAME when it names the front controller:
            // PHP's built-in server reports the request path instead for
            // extension-looking URLs (/bookmarklet/scraper.js).
            $script = str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? ''));
            $base = basename($script) === 'index.php' ? dirname($script) : '';
        }

        return rtrim($base, '/.');
    }

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    /** A root-relative app path ('/games/3') as a URL under the base. */
    public static function to(string $path): string
    {
        return self::$base . $path;
    }
}
