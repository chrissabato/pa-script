<?php

use App\Database\UpsertsBySlug;
use Phinx\Seed\AbstractSeed;

/** Safe to re-run: rows are matched by slug and updated in place. */
class SportsSeeder extends AbstractSeed
{
    use UpsertsBySlug;

    public function run(): void
    {
        $data = [
            ['name' => 'Soccer', 'slug' => 'soccer', 'uses_batting_order' => false, 'uses_libero' => false],
            ['name' => 'Basketball', 'slug' => 'basketball', 'uses_batting_order' => false, 'uses_libero' => false],
            ['name' => 'Baseball', 'slug' => 'baseball', 'uses_batting_order' => true, 'uses_libero' => false],
            ['name' => 'Softball', 'slug' => 'softball', 'uses_batting_order' => true, 'uses_libero' => false],
            ['name' => 'Volleyball', 'slug' => 'volleyball', 'uses_batting_order' => false, 'uses_libero' => true],
            ['name' => 'Lacrosse', 'slug' => 'lacrosse', 'uses_batting_order' => false, 'uses_libero' => false],
        ];

        $this->upsertBySlug('sports', $data);
    }
}
