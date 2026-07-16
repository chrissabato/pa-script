<?php

use Phinx\Seed\AbstractSeed;

class SchoolsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            ['name' => 'Willamette University', 'mascot' => 'Bearcats', 'short_name' => 'Willamette'],
        ];

        $this->table('schools')->insert($data)->saveData();
    }
}
