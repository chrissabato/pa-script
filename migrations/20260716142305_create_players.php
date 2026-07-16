<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePlayers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('players');
        $table
            ->addColumn('program_id', 'integer')
            ->addColumn('number', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('pronunciation', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('position', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('year', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('hometown', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('is_starter', 'boolean', ['default' => false])
            ->addColumn('batting_order', 'integer', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('program_id', 'programs', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['program_id'])
            ->create();
    }
}
