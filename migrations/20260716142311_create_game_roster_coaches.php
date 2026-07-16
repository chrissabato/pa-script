<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameRosterCoaches extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('game_roster_coaches');
        $table
            ->addColumn('snapshot_id', 'integer')
            ->addColumn('source_coach_id', 'integer', ['null' => true])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('title', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('snapshot_id', 'game_roster_snapshots', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('source_coach_id', 'coaches', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['snapshot_id'])
            ->create();
    }
}
