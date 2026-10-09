<?php

declare(strict_types=1);

namespace App\Teams;

use PDO;
use RuntimeException;

/**
 * Mirrors the athletics Team Data API into the local `schools` table.
 *
 * The API (https://athletics.willamette.edu/api/teams/docs) is the source of
 * truth for team names, mascots, logos, colors and websites — this app no
 * longer edits teams itself. `schools` is kept as a local cache rather than
 * calling the API per request so programs/games keep their foreign keys and
 * scripts still render if the API is unreachable at a game.
 */
class TeamApiSync
{
    public const BASE_URL = 'https://athletics.willamette.edu/api/teams';

    /** Placeholder entries in the API that aren't real opponents. */
    private const IGNORED_SLUGS = ['home', 'away'];

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $baseUrl;

    public function __construct(PDO $pdo, string $baseUrl = self::BASE_URL)
    {
        $this->pdo = $pdo;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Syncs only when the last successful sync is older than $maxAgeSeconds.
     * Never throws: returns an error message (the cached data stays usable),
     * or null when the cache is fresh or the sync succeeded.
     */
    public function syncIfStale(int $maxAgeSeconds): ?string
    {
        $last = $this->pdo->query('select max(synced_at) from schools')->fetchColumn();
        if ($last !== null && $last !== false && strtotime((string) $last . ' UTC') > time() - $maxAgeSeconds) {
            return null;
        }

        try {
            $this->sync();
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array{updated:int, inserted:int, removed:int, kept:array<int, string>}
     */
    public function sync(): array
    {
        $teams = $this->fetchTeams();
        $now = gmdate('Y-m-d H:i:s');

        $findBySlug = $this->pdo->prepare('select id from schools where slug = :slug');
        $findByName = $this->pdo->prepare('select id from schools where slug is null and name = :name');
        $update = $this->pdo->prepare(
            'update schools set slug = :slug, name = :name, variant = :variant, short_name = :short_name, mascot = :mascot, '
            . 'logo = :logo, color = :color, logo_bg = :logo_bg, url = :url, cms_provider = :cms_provider, '
            . 'api_updated_at = :api_updated_at, synced_at = :synced_at, updated_at = current_timestamp '
            . 'where id = :id'
        );
        $insert = $this->pdo->prepare(
            'insert into schools (slug, name, variant, short_name, mascot, logo, color, logo_bg, url, cms_provider, '
            . 'api_updated_at, synced_at) values (:slug, :name, :variant, :short_name, :mascot, :logo, :color, :logo_bg, '
            . ':url, :cms_provider, :api_updated_at, :synced_at)'
        );

        $result = ['updated' => 0, 'inserted' => 0, 'removed' => 0, 'kept' => []];
        $slugs = [];

        // Callers like Phinx seeders may already have a transaction open.
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($teams as $team) {
                $row = $this->mapTeam($team);
                if ($row === null) {
                    continue;
                }
                $row['synced_at'] = $now;
                $slugs[] = $row['slug'];

                $findBySlug->execute(['slug' => $row['slug']]);
                $id = $findBySlug->fetchColumn();
                if ($id === false) {
                    // First sync: adopt a pre-API row with the same name.
                    $findByName->execute(['name' => $row['name']]);
                    $id = $findByName->fetchColumn();
                }

                if ($id !== false) {
                    $update->execute(array_merge($row, ['id' => (int) $id]));
                    $result['updated']++;
                } else {
                    $insert->execute($row);
                    $result['inserted']++;
                }
            }

            // Local teams the API doesn't have: drop them unless a program
            // (and so possibly a game) still points at them.
            $stale = $this->pdo->query(
                'select s.id, s.name, s.slug, exists (select 1 from programs p where p.school_id = s.id) as used '
                . 'from schools s'
            )->fetchAll(PDO::FETCH_ASSOC);
            $delete = $this->pdo->prepare('delete from schools where id = :id');
            foreach ($stale as $school) {
                if ($school['slug'] !== null && in_array($school['slug'], $slugs, true)) {
                    continue;
                }
                if ($school['used']) {
                    $result['kept'][] = (string) $school['name'];
                    continue;
                }
                $delete->execute(['id' => (int) $school['id']]);
                $result['removed']++;
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchTeams(): array
    {
        // curl, not file_get_contents: the production PHP has no https://
        // stream wrapper (no openssl extension), but does have curl.
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The server is missing the PHP curl extension, so it cannot reach the team API.');
        }

        // Trailing slash matters: without it the API redirects first.
        $ch = curl_init($this->baseUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body)) {
            throw new RuntimeException('Could not reach the team API at ' . $this->baseUrl . '/: ' . $error);
        }

        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data) || !isset($data['teams']) || !is_array($data['teams'])) {
            throw new RuntimeException('Unexpected response from the team API (HTTP ' . $status . ').');
        }

        return $data['teams'];
    }

    /**
     * @param array<string, mixed> $team
     * @return array<string, string|null>|null
     */
    private function mapTeam(array $team): ?array
    {
        $slug = (string) ($team['slug'] ?? '');
        $name = trim((string) ($team['full_name'] ?? ''));
        if ($slug === '' || $name === '' || in_array($slug, self::IGNORED_SLUGS, true)) {
            return null;
        }

        $text = function ($value): ?string {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        };

        $url = $text($team['website'] ?? null);
        if ($url !== null && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $updatedAt = $text($team['updated_at'] ?? null);

        return [
            'slug' => $slug,
            'name' => $name,
            'variant' => $text($team['variant'] ?? null),
            'short_name' => $text($team['short_name'] ?? null),
            'mascot' => $text($team['mascot'] ?? null),
            'logo' => $text($team['logo']['url'] ?? null),
            'color' => $text($team['colors']['primary'] ?? null),
            'logo_bg' => $text($team['colors']['logo_bg'] ?? null),
            'url' => $url,
            // website_provider (was stats.provider before the API moved it).
            'cms_provider' => $text($team['website_provider'] ?? null) ?? $text($team['stats']['provider'] ?? null),
            'api_updated_at' => $updatedAt !== null ? gmdate('Y-m-d H:i:s', (int) strtotime($updatedAt)) : null,
        ];
    }
}
