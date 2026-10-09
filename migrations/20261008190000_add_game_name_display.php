<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * How the script shows people's names: 'name', 'pronunciation' (the
 * phonetic guide, falling back to the name — the original behavior), or
 * 'both' ("Siera Edwards (SEE-era ED-werds)").
 */
final class AddGameNameDisplay extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('name_display', 'string', ['limit' => 20, 'default' => 'pronunciation'])
            ->update();
    }
}
