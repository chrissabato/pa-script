<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSchools extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('schools');
        $table
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('mascot', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('short_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
