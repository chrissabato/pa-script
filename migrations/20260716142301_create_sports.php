<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSports extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('sports');
        $table
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('uses_batting_order', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}
