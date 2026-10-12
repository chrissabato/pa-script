<?php

use App\Site;
use App\Teams\TeamApiSync;
use Phinx\Seed\AbstractSeed;

/**
 * Teams come from the athletics Team Data API (TEAMS_API_URL, see
 * App\Site) — this seeder just runs
 * the same sync the app uses (see App\Teams\TeamApiSync and
 * bin/sync-teams.php), so a fresh database gets the real team list.
 * Requires network access to the API. Idempotent: safe to re-run.
 */
class SchoolsSeeder extends AbstractSeed
{
    public function run(): void
    {
        // Skip rather than fail, so a fresh install's other seeders still run.
        if (Site::teamsApiUrl() === '') {
            $this->getOutput()->writeln(' TEAMS_API_URL is not set in .env: skipping the team sync.');

            return;
        }

        $result = (new TeamApiSync($this->getAdapter()->getConnection(), Site::teamsApiUrl()))->sync();

        $this->getOutput()->writeln(sprintf(
            ' Teams synced from API: %d updated, %d added, %d removed.',
            $result['updated'],
            $result['inserted'],
            $result['removed']
        ));
    }
}
