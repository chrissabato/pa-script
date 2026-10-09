<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The team API's `variant`: "" unless a school has several entries — e.g.
 * Cal Lutheran's "Men" (Kingsmen) and "Women" (Regals), or Willamette's
 * "GOLD" alt logo. Shown next to the name, and used to pick the entry that
 * matches a game's division.
 */
final class AddSchoolVariant extends AbstractMigration
{
    public function change(): void
    {
        $this->table('schools')
            ->addColumn('variant', 'string', ['limit' => 50, 'null' => true])
            ->update();
    }
}
