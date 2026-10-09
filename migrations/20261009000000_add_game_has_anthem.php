<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Whether the national anthem is played before the game; the script drops
 * its anthem line when it isn't (the Overview's "No national anthem" box).
 */
final class AddGameHasAnthem extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('has_anthem', 'boolean', ['default' => true])
            ->update();
    }
}
