<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Rosters now exist only per game: each game side starts empty and is
 * filled by a roster-page fetch, the bookmarklet, or by hand. The
 * standing per-program `players`/`coaches` tables (and the snapshot rows'
 * links back to them) are gone. `programs` stays — games reference it and
 * it remembers each team's roster URL.
 *
 * down() restores the schema only; dropped roster rows are not recovered.
 */
final class DropPersistentRosters extends AbstractMigration
{
    public function up(): void
    {
        $this->table('game_roster_players')
            ->dropForeignKey('source_player_id')
            ->removeColumn('source_player_id')
            ->update();
        $this->table('game_roster_coaches')
            ->dropForeignKey('source_coach_id')
            ->removeColumn('source_coach_id')
            ->update();

        $this->table('players')->drop()->save();
        $this->table('coaches')->drop()->save();
    }

    public function down(): void
    {
        $this->table('players')
            ->addColumn('program_id', 'integer')
            ->addColumn('number', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('position', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('year', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('hometown', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('is_starter', 'boolean', ['default' => false])
            ->addColumn('batting_order', 'integer', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('program_id', 'programs', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['program_id'])
            ->create();

        $this->table('coaches')
            ->addColumn('program_id', 'integer')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('title', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('program_id', 'programs', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['program_id'])
            ->create();

        $this->table('game_roster_players')
            ->addColumn('source_player_id', 'integer', ['null' => true, 'after' => 'snapshot_id'])
            ->addForeignKey('source_player_id', 'players', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();
        $this->table('game_roster_coaches')
            ->addColumn('source_coach_id', 'integer', ['null' => true, 'after' => 'snapshot_id'])
            ->addForeignKey('source_coach_id', 'coaches', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
