<?php

use App\Teams\TeamApiSync;
use Phinx\Seed\AbstractSeed;

/**
 * Teams come from the athletics Team Data API
 * (https://athletics.willamette.edu/api/teams/docs) — this seeder just runs
 * the same sync the app uses (see App\Teams\TeamApiSync and
 * bin/sync-teams.php), so a fresh database gets the real team list.
 * Requires network access to the API. Idempotent: safe to re-run.
 */
class SchoolsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $result = (new TeamApiSync($this->getAdapter()->getConnection()))->sync();

        $this->getOutput()->writeln(sprintf(
            ' Teams synced from API: %d updated, %d added, %d removed.',
            $result['updated'],
            $result['inserted'],
            $result['removed']
        ));
    }
}
