<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Team (school) fields to match the broadcast app's teams.json: logo
 * (a filename under public/logos/ or an absolute URL), primary color
 * (#RRGGBB), athletics website URL, and the site's CMS provider
 * (sidearm, presto, presto_old) — the last two are what a roster
 * import needs to know where and how to scrape.
 */
final class AddSchoolBrandingFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('schools')
            ->addColumn('logo', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('color', 'string', ['limit' => 7, 'null' => true])
            ->addColumn('url', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('cms_provider', 'string', ['limit' => 30, 'null' => true])
            ->update();
    }
}
