<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameRosterPlayers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('game_roster_players');
        $table
            ->addColumn('snapshot_id', 'integer')
            ->addColumn('source_player_id', 'integer', ['null' => true])
            ->addColumn('number', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('position', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('year', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('hometown', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('is_starter', 'boolean', ['default' => false])
            ->addColumn('batting_order', 'integer', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('snapshot_id', 'game_roster_snapshots', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('source_player_id', 'players', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['snapshot_id'])
            ->create();
    }
}
