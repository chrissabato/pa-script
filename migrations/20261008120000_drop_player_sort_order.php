<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Players are always listed by jersey number, so the hand-maintained
 * sort_order column on persistent and per-game rosters is gone.
 * (Coaches keep theirs — head coach first isn't derivable.)
 */
final class DropPlayerSortOrder extends AbstractMigration
{
    public function change(): void
    {
        $this->table('players')
            ->removeColumn('sort_order')
            ->update();

        $this->table('game_roster_players')
            ->removeColumn('sort_order')
            ->update();
    }
}
