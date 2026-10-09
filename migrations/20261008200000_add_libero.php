<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Volleyball's libero (the source sheet's LIBERO checkbox): a per-game
 * player flag, introduced as "At libero, ..." and read last in each group.
 * `sports.uses_libero` decides which sports show the checkbox — data, like
 * `uses_batting_order`, not a sport-name check in code.
 */
final class AddLibero extends AbstractMigration
{
    public function change(): void
    {
        $this->table('game_roster_players')
            ->addColumn('is_libero', 'boolean', ['default' => false])
            ->update();

        $this->table('sports')
            ->addColumn('uses_libero', 'boolean', ['default' => false])
            ->update();

        if ($this->isMigratingUp()) {
            $this->execute("update sports set uses_libero = true where slug = 'volleyball'");
        }
    }
}
