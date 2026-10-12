<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * programs.is_willamette -> is_own_school: the flag marks the install's own
 * school (HOME_TEAM_SLUG), whichever school that is.
 */
final class RenameProgramsIsWillamette extends AbstractMigration
{
    public function change(): void
    {
        $this->table('programs')
            ->renameColumn('is_willamette', 'is_own_school')
            ->update();
    }
}
