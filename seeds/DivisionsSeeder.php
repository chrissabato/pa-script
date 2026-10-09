<?php

use App\Database\UpsertsBySlug;
use Phinx\Seed\AbstractSeed;

/** Safe to re-run: rows are matched by slug and updated in place. */
class DivisionsSeeder extends AbstractSeed
{
    use UpsertsBySlug;

    public function run(): void
    {
        $data = [
            ['name' => "Women's", 'slug' => 'womens'],
            ['name' => "Men's", 'slug' => 'mens'],
        ];

        $this->upsertBySlug('divisions', $data);
    }
}
