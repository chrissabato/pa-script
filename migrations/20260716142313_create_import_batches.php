<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateImportBatches extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('import_batches');
        $table
            ->addColumn('source_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('raw_payload', 'jsonb')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
