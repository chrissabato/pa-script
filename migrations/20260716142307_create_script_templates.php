<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateScriptTemplates extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('script_templates');
        $table
            ->addColumn('sport_id', 'integer')
            ->addColumn('division_id', 'integer', ['null' => true])
            ->addColumn('site_variant', 'string', ['limit' => 20, 'default' => 'home'])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('content_blocks', 'jsonb')
            ->addColumn('version', 'integer', ['default' => 1])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('sport_id', 'sports', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('division_id', 'divisions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addIndex(['sport_id', 'division_id', 'site_variant'], ['unique' => true, 'name' => 'idx_script_templates_sport_division_variant'])
            ->create();
    }
}
