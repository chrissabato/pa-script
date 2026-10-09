<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * An optional announcement read at the very end of the script (the special
 * announcement is read just before the anthem).
 */
final class AddGameClosingAnnouncement extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('closing_announcement', 'text', ['null' => true])
            ->update();
    }
}
