<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Where a program's roster lives on its school's athletics site, so the
 * roster page can fetch and import it. Null until someone saves or
 * fetches one — the page suggests a URL from the school's site + CMS
 * provider in the meantime (App\Roster\RosterUrlGuesser).
 */
final class AddProgramRosterUrl extends AbstractMigration
{
    public function change(): void
    {
        $this->table('programs')
            ->addColumn('roster_url', 'string', ['limit' => 500, 'null' => true])
            ->update();
    }
}
