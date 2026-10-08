<?php

declare(strict_types=1);

namespace App\Roster;

/**
 * Fetches a school's roster page server-side so the roster page can parse
 * it (in the browser, with the same scraper-core.js the bookmarklet uses).
 *
 * The URL is user-supplied, so this only fetches public http(s) hosts:
 * every hop's hostname is resolved, rejected if it points at a private or
 * reserved address, and curl is pinned to the vetted IP so a second DNS
 * lookup can't swap it. Redirects are followed by hand for the same
 * reason.
 *
 * Some athletics sites (e.g. wubearcats.com) sit behind an AWS WAF
 * JavaScript challenge that no server-side client can pass unless the
 * server is allowlisted; that's reported as RosterFetchBlocked so the UI
 * can steer the user to the bookmarklet or paste-HTML fallbacks.
 */
class RosterPageFetcher
{
    private const MAX_REDIRECTS = 5;
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 20;
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/130.0.0.0 Safari/537.36';

    /**
     * @return array{html: string, url: string} the page body and the final URL after redirects
     * @throws RosterFetchException
     */
    public function fetch(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new RosterFetchException('The server is missing the PHP curl extension, so it cannot fetch roster pages.');
        }

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->vet($url);
            [$status, $headers, $body] = $this->request($url, $host, $port, $ip);

            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $url = $this->resolveRedirect($url, $headers['location']);
                continue;
            }

            if (isset($headers['x-amzn-waf-action']) || ($status === 202 && trim($body) === '')) {
                throw new RosterFetchBlocked(
                    $host . ' blocked the server\'s request with a bot check. Open the page in your browser and '
                    . 'use the bookmarklet, or paste the page HTML below.'
                );
            }
            if ($status === 403 || $status === 429) {
                throw new RosterFetchBlocked($host . ' refused the server\'s request (HTTP ' . $status . ').');
            }
            if ($status !== 200) {
                throw new RosterFetchException($host . ' returned HTTP ' . $status . ' for that URL — check it is the roster page.');
            }

            return ['html' => $body, 'url' => $url];
        }

        throw new RosterFetchException('Too many redirects.');
    }

    /**
     * @return array{0: string, 1: int, 2: string} host, port, and the public IP to connect to
     * @throws RosterFetchException
     */
    private function vet(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RosterFetchException('Enter a full http(s):// roster URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RosterFetchException('Roster URLs cannot contain a username or password.');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443], true)) {
            throw new RosterFetchException('Roster URLs must use the standard web ports.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new RosterFetchException('Could not find the site ' . $host . '.');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RosterFetchException('That URL points at a private address; only public sites can be fetched.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /**
     * @return array{0: int, 1: array<string, string>, 2: string}
     * @throws RosterFetchException
     */
    private function request(string $url, string $host, int $port, string $ip): array
    {
        $headers = [];
        $body = '';
        $tooBig = false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$tooBig): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooBig = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($tooBig) {
            throw new RosterFetchException('That page is too large to be a roster page.');
        }
        if ($ok === false) {
            throw new RosterFetchException('Could not reach ' . $host . ': ' . $error);
        }

        return [$status, $headers, $body];
    }

    private function resolveRedirect(string $from, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($from);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }
        if (strpos($location, '/') === 0) {
            return $origin . $location;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $origin . $dir . $location;
    }
}
