<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * When a template was last saved from the in-app editor; null = still the
 * default wording, which ScriptTemplatesSeeder keeps up to date.
 */
final class AddScriptTemplateEditedAt extends AbstractMigration
{
    public function change(): void
    {
        $this->table('script_templates')
            ->addColumn('edited_at', 'timestamp', ['null' => true])
            ->update();
    }
}
