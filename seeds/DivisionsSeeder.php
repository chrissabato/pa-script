<?php

use Phinx\Seed\AbstractSeed;

class DivisionsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            ['name' => "Women's", 'slug' => 'womens'],
            ['name' => "Men's", 'slug' => 'mens'],
        ];

        $this->table('divisions')->insert($data)->saveData();
    }
}
