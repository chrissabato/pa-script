<?php

use Phinx\Seed\AbstractSeed;

class ProgramsSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [
            SportsSeeder::class,
            DivisionsSeeder::class,
            SchoolsSeeder::class,
        ];
    }

    public function run(): void
    {
        $schoolId = $this->fetchRow("select id from schools where short_name = 'Willamette'")['id'];
        $soccerId = $this->fetchRow("select id from sports where slug = 'soccer'")['id'];
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $data = [
            [
                'school_id' => $schoolId,
                'sport_id' => $soccerId,
                'division_id' => $womensId,
                'is_willamette' => true,
            ],
            [
                'school_id' => $schoolId,
                'sport_id' => $soccerId,
                'division_id' => $mensId,
                'is_willamette' => true,
            ],
        ];

        $this->table('programs')->insert($data)->saveData();
    }
}
