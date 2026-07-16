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
        $basketballId = $this->fetchRow("select id from sports where slug = 'basketball'")['id'];
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $wanted = [
            ['school_id' => $schoolId, 'sport_id' => $soccerId, 'division_id' => $womensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $soccerId, 'division_id' => $mensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $basketballId, 'division_id' => $womensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $basketballId, 'division_id' => $mensId, 'is_willamette' => true],
        ];

        $data = array_values(array_filter($wanted, function (array $row): bool {
            $existing = $this->fetchRow(sprintf(
                'select id from programs where school_id = %d and sport_id = %d and division_id = %d',
                $row['school_id'],
                $row['sport_id'],
                $row['division_id']
            ));

            return $existing === false;
        }));

        if ($data !== []) {
            $this->table('programs')->insert($data)->saveData();
        }
    }
}
