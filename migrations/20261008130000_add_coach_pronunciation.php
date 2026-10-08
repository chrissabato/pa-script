<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Coaches get the same phonetic-pronunciation field players have, on both
 * the persistent roster and per-game snapshots.
 */
final class AddCoachPronunciation extends AbstractMigration
{
    public function change(): void
    {
        $this->table('coaches')
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true, 'after' => 'name'])
            ->update();

        $this->table('game_roster_coaches')
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true, 'after' => 'name'])
            ->update();
    }
}
