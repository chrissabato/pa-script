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
        $baseballId = $this->fetchRow("select id from sports where slug = 'baseball'")['id'];
        $softballId = $this->fetchRow("select id from sports where slug = 'softball'")['id'];
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $wanted = [
            ['school_id' => $schoolId, 'sport_id' => $soccerId, 'division_id' => $womensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $soccerId, 'division_id' => $mensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $basketballId, 'division_id' => $womensId, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $basketballId, 'division_id' => $mensId, 'is_willamette' => true],
            // Baseball/Softball are single-team sports in practice — no division per PLAN.md.
            ['school_id' => $schoolId, 'sport_id' => $baseballId, 'division_id' => null, 'is_willamette' => true],
            ['school_id' => $schoolId, 'sport_id' => $softballId, 'division_id' => null, 'is_willamette' => true],
        ];

        $data = array_values(array_filter($wanted, function (array $row): bool {
            $divisionClause = $row['division_id'] === null ? 'is null' : '= ' . (int) $row['division_id'];
            $existing = $this->fetchRow(sprintf(
                'select id from programs where school_id = %d and sport_id = %d and division_id %s',
                $row['school_id'],
                $row['sport_id'],
                $divisionClause
            ));

            return $existing === false;
        }));

        if ($data !== []) {
            $this->table('programs')->insert($data)->saveData();
        }
    }
}
