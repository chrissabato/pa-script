<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameRosterSnapshots extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('game_roster_snapshots');
        $table
            ->addColumn('game_id', 'integer')
            ->addColumn('program_id', 'integer', ['null' => true])
            ->addColumn('side', 'string', ['limit' => 10])
            ->addColumn('captured_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('game_id', 'games', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('program_id', 'programs', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['game_id', 'side'], ['unique' => true, 'name' => 'idx_game_roster_snapshots_game_side'])
            ->create();
    }
}
