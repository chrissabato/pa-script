<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Baseball/Softball's per-game lineup: the position a player is playing
 * today ("right field", "designated player"), typed fresh each game like
 * the sheets' Setup tab — separate from their roster position ("P/OF").
 * Their batting slot is the existing `batting_order` (1–9, 10 = the flex).
 * `sports.uses_flex` (Softball) offers the Flex slot — a flag like
 * `uses_libero`, not a sport-name check.
 */
final class AddLineupPosition extends AbstractMigration
{
    public function change(): void
    {
        $this->table('game_roster_players')
            ->addColumn('lineup_position', 'string', ['limit' => 50, 'null' => true])
            ->update();

        $this->table('sports')
            ->addColumn('uses_flex', 'boolean', ['default' => false])
            ->update();

        if ($this->isMigratingUp()) {
            $this->execute("update sports set uses_flex = true where slug = 'softball'");
        }
    }
}
