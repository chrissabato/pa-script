<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDivisions extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('divisions');
        $table
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}
