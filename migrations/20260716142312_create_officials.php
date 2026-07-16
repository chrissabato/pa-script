<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOfficials extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('officials');
        $table
            ->addColumn('game_id', 'integer')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('role', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('game_id', 'games', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['game_id'])
            ->create();
    }
}
