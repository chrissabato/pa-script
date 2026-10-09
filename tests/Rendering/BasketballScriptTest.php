<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The Basketball (home) templates against the real "PA Script - Women's
 * Basketball" sheet (Willamette vs Pacific, evening). Expected lines are the
 * sheet's own rendered Script-tab values, except where the app deliberately
 * differs — those are called out next to each assertion.
 */
class BasketballScriptTest extends TestCase
{
    /** Home/Away tab rows: number, name, phonetic, pos, year, hometown. */
    private const HOME_STARTERS = [
        ['10', 'Peyton Gardner', 'PAY-ton GARD-ner', 'G', 'Sr.', 'Bend, Oregon'],
        ['11', 'Kaitlin Imai', 'KATE-lin EE-my', 'G', 'Sr.', 'Santa Cruz, California'],
        ['12', 'Riley Walden', 'RYE-lee WALL-din', 'G', 'Fy.', 'Tehachapi, California'],
        ['14', 'Daisy Boling-Schmidt', 'DAY-zee BOW-ling-SHMIT', 'C', 'So.', 'Oregon City, Oregon'],
        ['30', 'Emma Floyd', 'EM-uh FLOYD', 'F', 'Sr.', 'Camano Island, Washington'],
    ];

    private const AWAY_STARTERS = [
        ['2', 'Ava Cook', '', 'F', 'Sr.', 'Stanwood, Wash.'],
        ['3', 'Shaade Williams', 'Sha-day Williams', 'G', 'Sr.', 'San Jose, Calif.'],
        ['4', 'Lindsay Barden', '', 'G', 'Sr.', 'Gresham, Ore.'],
        ['14', 'Sydney Esperanza', '', 'G', 'So.', 'Renton, WA'],
        ['30', 'Karina Franco', 'Ka-rina Fron-co', 'F', 'Fr.', 'Portland, Ore.'],
    ];

    private static function blocks(string $divisionLabel): array
    {
        require_once __DIR__ . '/../../seeds/ScriptTemplatesSeeder.php';
        $method = new \ReflectionMethod(\ScriptTemplatesSeeder::class, 'basketballHomeBlocks');
        $method->setAccessible(true);

        return $method->invoke(new \ScriptTemplatesSeeder(), $divisionLabel);
    }

    private static function players(array $rows): array
    {
        static $id = 0;

        return array_map(function (array $row) use (&$id): array {
            [$number, $name, $phonetic, $pos, $year, $hometown] = $row;

            return [
                'id' => ++$id, 'number' => $number, 'name' => $name, 'pronunciation' => $phonetic,
                'position' => $pos, 'year' => $year, 'hometown' => $hometown,
                'is_starter' => true, 'is_captain' => false, 'batting_order' => null,
            ];
        }, $rows);
    }

    private static function coach(int $id, string $name, string $title, ?string $pronoun = null, ?int $seasons = null): array
    {
        return ['id' => $id, 'name' => $name, 'pronunciation' => null, 'title' => $title, 'pronoun' => $pronoun, 'seasons' => $seasons, 'sort_order' => $id];
    }

    private function render(string $divisionLabel, bool $hasAnthem = true): string
    {
        $game = [
            'id' => 1, 'sport_slug' => 'basketball', 'time_of_day' => '19:00:00', 'is_conference' => true,
            'venue' => null, 'special_announcement' => null, 'has_anthem' => $hasAnthem,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'Pacific University', 'away_short_name' => 'Pacific', 'away_mascot' => 'Boxers',
        ];
        $context = GameContextBuilder::assemble($game, [
            'home' => ['players' => self::players(self::HOME_STARTERS), 'coaches' => [
                self::coach(1, 'Peg Swaydener', 'Head Coach', 'her', 13),
                self::coach(2, 'Grace Barry', 'Assistant Coach'),
                self::coach(3, 'Sarah Hedgepeth', 'Assistant Coach'),
                self::coach(4, 'Jaelyn Two Hearts', 'Assistant Coach'),
                self::coach(5, 'Ashley Corral', 'Assistant Coach'),
            ]],
            'away' => ['players' => self::players(self::AWAY_STARTERS), 'coaches' => [
                self::coach(6, 'Elisha Parker', 'Head Coach', 'her', 8),
                self::coach(7, 'Abby Anderson', 'Assistant Coach'),
                self::coach(8, 'Hi-a Koo', 'Assistant Coach'),
                self::coach(9, 'Kaylee-Brooke Manuel', 'Assistant Coach'),
            ]],
        ], [
            ['name' => 'Rhonda Daws', 'role' => null], ['name' => 'Raynel Whitney', 'role' => null],
            ['name' => 'John Gullic', 'role' => null],
        ]);

        return (new TemplateRenderer())->render(self::blocks($divisionLabel), $context);
    }

    public function testWomensMatchesSheet(): void
    {
        $script = $this->render("Women's");

        // The sheet says "today's" here: its evening check compares against
        // "evening " (trailing space), so it never matches.
        self::assertStringStartsWith(
            "Good evening and welcome to Willamette University's Cone Field House on the land of the Kalapuya for "
            . "tonight's Northwest Conference Women's Basketball game between the Pacific University Boxers and the "
            . "Willamette University Bearcats. And now for the playing of the National Anthem.\n\n"
            . "Before the game gets underway, let's meet the starting lineups.\n\n"
            . "First for the visitors from Pacific\n",
            $script
        );
        // Away hometowns: the sheet leaves "Stanwood, Wash." as typed; the
        // app reads every state in full, like the home side.
        self::assertStringContainsString(
            "First for the visitors from Pacific\n"
            . "At forward, a senior from Stanwood, Washington, number 2, Ava Cook\n"
            . "At guard, a senior from San Jose, California, number 3, Sha-day Williams\n"
            . "At guard, a senior from Gresham, Oregon, number 4, Lindsay Barden\n"
            . "At guard, a sophomore from Renton, Washington, number 14, Sydney Esperanza\n"
            . "At forward, a first year from Portland, Oregon, number 30, Ka-rina Fron-co\n"
            // Coach lines: the app lists assistants "A, B, and C" and ends with a period.
            . "Pacific's head coach is Elisha Parker in her 8th season and is assisted by Abby Anderson, Hi-a Koo, "
            . "and Kaylee-Brooke Manuel.\n"
            // From the Men's sheet; both divisions get the walk-out cue.
            . "\n[START Joker and the Thief]\n"
            . "\nAnd now, the starting lineup for the Willamette University Bearcats\n"
            . "At guard, a senior from Bend, Oregon, number 10, PAY-ton GARD-ner\n"
            . "At guard, a senior from Santa Cruz, California, number 11, KATE-lin EE-my\n"
            . "At guard, a first year from Tehachapi, California, number 12, RYE-lee WALL-din\n"
            . "At center, a sophomore from Oregon City, Oregon, number 14, DAY-zee BOW-ling-SHMIT\n"
            . "At forward, a senior from Camano Island, Washington, number 30, EM-uh FLOYD\n"
            . "Willamette's head coach is Peg Swaydener in her 13th season and is assisted by Grace Barry, "
            . "Sarah Hedgepeth, Jaelyn Two Hearts, and Ashley Corral.\n"
            . "\nThe officials for tonight's game are Rhonda Daws, Raynel Whitney, and John Gullic.\n",
            $script
        );
        self::assertStringContainsString('grounds for removal from Cone Field House.', $script);
        self::assertStringContainsString(
            "Willamette appreciates your cooperation in creating a safe and positive game environment.\n",
            $script
        );
        self::assertStringEndsWith("Apple App Store and check in to get your points.\n", $script);
        self::assertStringNotContainsString('{{', $script);
    }

    public function testMensMatchesWomensApartFromDivision(): void
    {
        self::assertSame(
            str_replace("Women's Basketball", "Men's Basketball", $this->render("Women's")),
            $this->render("Men's")
        );
    }

    public function testNoAnthemDropsTheAnthemLine(): void
    {
        self::assertStringContainsString(
            "and the Willamette University Bearcats.\n\nBefore the game gets underway,",
            $this->render("Women's", false)
        );
    }
}
