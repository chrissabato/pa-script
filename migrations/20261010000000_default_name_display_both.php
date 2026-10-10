<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * New games show names as "Name (PRONUNCIATION)" by default. Existing games
 * keep whatever they have.
 */
final class DefaultNameDisplayBoth extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("alter table games alter column name_display set default 'both'");
    }

    public function down(): void
    {
        $this->execute("alter table games alter column name_display set default 'pronunciation'");
    }
}
