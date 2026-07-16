<?php

use Phinx\Seed\AbstractSeed;

class SportsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            ['name' => 'Soccer', 'slug' => 'soccer', 'uses_batting_order' => false],
            ['name' => 'Basketball', 'slug' => 'basketball', 'uses_batting_order' => false],
            ['name' => 'Baseball', 'slug' => 'baseball', 'uses_batting_order' => true],
            ['name' => 'Softball', 'slug' => 'softball', 'uses_batting_order' => true],
            ['name' => 'Volleyball', 'slug' => 'volleyball', 'uses_batting_order' => false],
            ['name' => 'Lacrosse', 'slug' => 'lacrosse', 'uses_batting_order' => false],
        ];

        $this->table('sports')->insert($data)->saveData();
    }
}
