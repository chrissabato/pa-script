<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGames extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('games');
        $table
            ->addColumn('sport_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('time_of_day', 'time', ['null' => true])
            ->addColumn('home_program_id', 'integer', ['null' => true])
            ->addColumn('away_program_id', 'integer', ['null' => true])
            ->addColumn('is_neutral_site', 'boolean', ['default' => false])
            ->addColumn('neutral_site_note', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('venue', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('script_template_id', 'integer', ['null' => true])
            ->addColumn('anthem_note', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('sport_id', 'sports', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('home_program_id', 'programs', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('away_program_id', 'programs', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('script_template_id', 'script_templates', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addIndex(['date'])
            ->create();
    }
}
