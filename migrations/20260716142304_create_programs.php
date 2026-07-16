<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePrograms extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('programs');
        $table
            ->addColumn('school_id', 'integer')
            ->addColumn('sport_id', 'integer')
            ->addColumn('division_id', 'integer', ['null' => true])
            ->addColumn('is_willamette', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('school_id', 'schools', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('sport_id', 'sports', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('division_id', 'divisions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addIndex(['school_id', 'sport_id', 'division_id'], ['unique' => true, 'name' => 'idx_programs_school_sport_division'])
            ->create();
    }
}
