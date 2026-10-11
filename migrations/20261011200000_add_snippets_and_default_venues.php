<?php
declare(strict_types=1);

use App\Templates\DefaultTemplates;
use Phinx\Migration\AbstractMigration;

/**
 * Global script snippets (wording shared by every template, e.g. the
 * sportsmanship statement, used in a template as [snippet Name]) and each
 * sport's default venue, which the script reads when a home game has no
 * venue typed in.
 */
final class AddSnippetsAndDefaultVenues extends AbstractMigration
{
    public function up(): void
    {
        $this->table('script_snippets')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('content_blocks', 'json')
            ->addColumn('edited_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this->table('sports')
            ->addColumn('default_venue', 'string', ['limit' => 150, 'null' => true])
            ->update();

        $update = $this->getAdapter()->getConnection()
            ->prepare('update sports set default_venue = :venue where slug = :slug and default_venue is null');
        foreach (DefaultTemplates::VENUES as $slug => $venue) {
            $update->execute(['venue' => $venue, 'slug' => $slug]);
        }
    }

    public function down(): void
    {
        $this->table('sports')->removeColumn('default_venue')->update();
        $this->table('script_snippets')->drop()->save();
    }
}
