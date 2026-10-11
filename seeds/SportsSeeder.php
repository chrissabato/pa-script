<?php

use App\Database\UpsertsBySlug;
use App\Templates\DefaultTemplates;
use Phinx\Seed\AbstractSeed;

/**
 * Safe to re-run: rows are matched by slug and updated in place. Default
 * venues are only filled in where blank — they're edited in the app.
 */
class SportsSeeder extends AbstractSeed
{
    use UpsertsBySlug;

    public function run(): void
    {
        $data = [
            ['name' => 'Soccer', 'slug' => 'soccer', 'uses_batting_order' => false, 'uses_libero' => false, 'uses_flex' => false],
            ['name' => 'Basketball', 'slug' => 'basketball', 'uses_batting_order' => false, 'uses_libero' => false, 'uses_flex' => false],
            ['name' => 'Baseball', 'slug' => 'baseball', 'uses_batting_order' => true, 'uses_libero' => false, 'uses_flex' => false],
            ['name' => 'Softball', 'slug' => 'softball', 'uses_batting_order' => true, 'uses_libero' => false, 'uses_flex' => true],
            ['name' => 'Volleyball', 'slug' => 'volleyball', 'uses_batting_order' => false, 'uses_libero' => true, 'uses_flex' => false],
            ['name' => 'Lacrosse', 'slug' => 'lacrosse', 'uses_batting_order' => false, 'uses_libero' => false, 'uses_flex' => false],
        ];

        $this->upsertBySlug('sports', $data);

        $update = $this->getAdapter()->getConnection()
            ->prepare('update sports set default_venue = :venue where slug = :slug and default_venue is null');
        foreach (DefaultTemplates::VENUES as $slug => $venue) {
            $update->execute(['venue' => $venue, 'slug' => $slug]);
        }
    }
}
