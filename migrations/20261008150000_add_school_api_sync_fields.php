<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Teams now come from the athletics Team Data API
 * (https://athletics.willamette.edu/api/teams/docs); `schools` becomes a
 * local, read-only cache of it so scripts still render if the API is
 * unreachable on game day. Rows are keyed by the API's slug, which the
 * API documents as the stable identifier (names get edited).
 */
final class AddSchoolApiSyncFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('schools')
            ->addColumn('slug', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('logo_bg', 'string', ['limit' => 7, 'null' => true])
            ->addColumn('api_updated_at', 'timestamp', ['null' => true])
            ->addColumn('synced_at', 'timestamp', ['null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->update();

        if ($this->isMigratingUp()) {
            // The API lists Calvin as "Calvin College"; link the existing
            // row (which has games) to it so the first sync updates it in
            // place instead of creating a duplicate.
            $this->execute("update schools set slug = 'calvin-college' where name = 'Calvin University'");
        }
    }
}
