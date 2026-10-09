<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A hand-edited copy of the game's script. When set, the Script tab and
 * teleprompter show it instead of the generated script; null means "use the
 * generated script" (Reset on the Customize page).
 */
final class AddGameCustomScript extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('custom_script', 'text', ['null' => true])
            ->update();
    }
}
