<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The production server's Postgres predates jsonb (9.4), so the JSON
 * columns are plain json — the create migrations now say so. This brings
 * databases created with the old jsonb columns in line (a no-op where
 * they are already json). The app only stores and decodes these values,
 * never queries into them or compares them, so json loses nothing.
 */
final class JsonColumnsForPostgres93 extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('alter table script_templates alter column content_blocks type json using content_blocks::text::json');
        $this->execute('alter table import_batches alter column raw_payload type json using raw_payload::text::json');
    }

    public function down(): void
    {
        $this->execute('alter table script_templates alter column content_blocks type jsonb using content_blocks::text::jsonb');
        $this->execute('alter table import_batches alter column raw_payload type jsonb using raw_payload::text::jsonb');
    }
}
