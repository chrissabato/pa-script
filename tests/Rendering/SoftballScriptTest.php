<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\PlaceholderRegistry;
use App\Rendering\ScriptChecks;
use App\Rendering\TemplateRenderer;
use App\Templates\DefaultTemplates;
use PHPUnit\Framework\TestCase;

/**
 * The Softball (home) template against the real "PA Script - Softball"
 * sheet (Willamette vs Lewis & Clark). Expected lines are the sheet's
 * Script-tab wording; where its formulas misfire (swapped year/hometown
 * lookups, "Batting 3nd", a second "Batting 6th") the expected line is
 * what the formula meant to read, called out next to each assertion.
 */
class SoftballScriptTest extends TestCase
{
    /**
     * Willamette's lineup as the sheet's Setup tab lists it (slot, then the
     * player's Home-tab row: number, name, phonetic, pos, year, hometown),
     * plus where each is playing today. Slot 10 is the flex.
     */
    private const HOME_LINEUP = [
        [1, '2', 'Maris Pinheiro', 'MARE-is pa-NAIR-oh', 'C/OF', 'Jr.', 'Tulare, California', 'right field'],
        [2, '13', 'Maggie Fleischmann', 'MAG-ee FLY-sh-man', 'INF', 'Jr.', 'Fresno, California', '3rd base'],
        [3, '14', 'Mckenzee Petersen', '', 'P', 'Fy.', 'Scotts Mills, Oregon', '1st base'],
        [4, '44', 'Mia Atkins', '', '2B/3B', 'Fy.', 'Sammamish, Washington', 'designated player'],
        [5, '6', 'Maya Filger', 'MY-uh FILL-ger', 'P/OF', 'So.', 'Sacramento, California', 'pitcher'],
        [6, '8', 'Desirae Hatfield', 'DEZ-uh-ray HAT-field', 'INF', 'Jr.', 'Salem, Oregon', 'shortstop'],
        [7, '4', 'Jenna Hopkins', 'JEN-uh HOP-kins', 'INF', 'Jr.', 'Mt. Angel, Oregon', '2nd base'],
        [8, '12', 'Emma Elliott', '', 'OF', 'Sr.', 'Battle Ground, Washington', 'left field'],
        [9, '18', 'Gretchen Clower', 'GRECH-in CLOW-er', 'OF', 'Fy.', 'Phoenix, Arizona', 'center field'],
        [10, '27', 'Karsen Angel', 'CAR-sin AINE-je', 'C', 'Jr.', 'Albany, Oregon', 'catcher'],
    ];

    /** The sheet's Script!C19:C28, as its formulas meant them to read. */
    private const SHEET_HOME_LINES = [
        'Leading off at right field, a junior from Tulare, California, number 2, MARE-is pa-NAIR-oh',
        'Batting 2nd at 3rd base, a junior from Fresno, California, number 13, MAG-ee FLY-sh-man',
        'Batting 3rd at 1st base, a first year from Scotts Mills, Oregon, number 14, Mckenzee Petersen',
        'Batting 4th at designated player, a first year from Sammamish, Washington, number 44, Mia Atkins',
        'Batting 5th at pitcher, a sophomore from Sacramento, California, number 6, MY-uh FILL-ger',
        'Batting 6th at shortstop, a junior from Salem, Oregon, number 8, DEZ-uh-ray HAT-field',
        'Batting 7th at 2nd base, a junior from Mt. Angel, Oregon, number 4, JEN-uh HOP-kins',
        'Batting 8th at left field, a senior from Battle Ground, Washington, number 12, Emma Elliott',
        'Batting 9th at center field, a first year from Phoenix, Arizona, number 18, GRECH-in CLOW-er',
        'In the flex at catcher, a junior from Albany, Oregon, number 27, CAR-sin AINE-je',
    ];

    /** @var array<int, array<string, mixed>> */
    private $blocks;

    protected function setUp(): void
    {
        $this->blocks = (new DefaultTemplates())->softballHomeBlocks();
    }

    private static function player(?int $slot, string $number, string $name, string $phonetic, string $pos, string $year, string $hometown, string $playing = ''): array
    {
        static $id = 0;

        return [
            'id' => ++$id, 'number' => $number, 'name' => $name, 'pronunciation' => $phonetic,
            'position' => $pos, 'year' => $year, 'hometown' => $hometown,
            'is_starter' => false, 'is_captain' => false, 'batting_order' => $slot, 'lineup_position' => $playing,
        ];
    }

    private static function coach(int $id, string $name, string $title, ?string $pronoun = null, ?int $seasons = null): array
    {
        return ['id' => $id, 'name' => $name, 'pronunciation' => null, 'title' => $title, 'pronoun' => $pronoun, 'seasons' => $seasons, 'sort_order' => $id];
    }

    private static function game(array $overrides = []): array
    {
        return array_merge([
            'id' => 1, 'sport_slug' => 'softball', 'division_slug' => null, 'sport_default_venue' => DefaultTemplates::VENUES['softball'], 'uses_batting_order' => true, 'name_display' => 'pronunciation',
            'time_of_day' => '13:00:00', 'is_conference' => true,
            'venue' => null, 'special_announcement' => null,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'Lewis & Clark College', 'away_short_name' => 'Lewis & Clark', 'away_mascot' => 'River Otters',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private static function context(array $gameOverrides = [], array $awayPlayers = [], array $officials = []): array
    {
        $homePlayers = array_map(function (array $row): array {
            return self::player(...$row);
        }, self::HOME_LINEUP);
        // A bench player: on the roster, not in the lineup.
        $homePlayers[] = self::player(null, '3', 'Reese Wiseman', '', 'INF', 'So.', 'Bend, Oregon');

        return GameContextBuilder::assemble(self::game($gameOverrides), [
            'home' => ['players' => $homePlayers, 'coaches' => [
                self::coach(1, 'Paige Hall', 'Head Coach', 'her', 6),
                self::coach(2, 'Allie Rude', 'Assistant Coach'),
                self::coach(3, 'Tommy Riedman', 'Assistant Coach'),
            ]],
            'away' => ['players' => $awayPlayers, 'coaches' => [
                self::coach(4, 'Shawna Cyrus', 'Head Coach', 'her', 17),
                self::coach(5, 'Shianne Smith', 'Assistant Coach'),
            ]],
        ], $officials);
    }

    private function render(array $gameOverrides = [], array $awayPlayers = [], array $officials = []): string
    {
        return (new TemplateRenderer((new DefaultTemplates())->snippets()))->render($this->blocks, self::context($gameOverrides, $awayPlayers, $officials));
    }

    public function testWelcomeMatchesSheet(): void
    {
        self::assertStringStartsWith(
            // The shared Welcome snippet: no "ladies and gentlemen," and the
            // team reads "Softball".
            "Good afternoon and welcome to Willamette Softball Field on the land of the Kalapuya "
            . "for today's Northwest Conference Softball game between the Lewis & Clark College River Otters and the "
            . "Willamette University Bearcats. Before the game gets underway, let's meet the starters.\n\n"
            . "First for the visitors from Lewis & Clark\n",
            $this->render()
        );
    }

    public function testHomeBattingOrderMatchesSheet(): void
    {
        $script = $this->render();

        self::assertStringContainsString(
            "And now, the starting lineup for the Willamette University Bearcats\n"
            . implode("\n", self::SHEET_HOME_LINES) . "\n"
            . "Willamette's head coach is Paige Hall in her 6th season and is assisted by Allie Rude and Tommy Riedman.\n",
            $script
        );
        self::assertStringNotContainsString('Reese Wiseman', $script);
    }

    public function testAwayLineupFallsBackToRosterPosition(): void
    {
        // No "Playing" position typed: the roster position is read aloud.
        $script = $this->render([], [
            self::player(1, '21', 'Kayla Kodani', '', 'SS', 'Sr.', 'Honolulu, Hawaii'),
            self::player(2, '18', 'Jordan Ingram', '', 'C/OF', 'Jr.', 'Tacoma, Wash.', 'first base'),
        ]);

        // Sheet: "Shawna Cyrusin her 17th season" — the formula's missing space.
        self::assertStringContainsString(
            "First for the visitors from Lewis & Clark\n"
            . "Leading off at shortstop, a senior from Honolulu, Hawaii, number 21, Kayla Kodani\n"
            . "Batting 2nd at first base, a junior from Tacoma, Washington, number 18, Jordan Ingram\n"
            . "Lewis & Clark's head coach is Shawna Cyrus in her 17th season and is assisted by Shianne Smith.\n",
            $script
        );
    }

    public function testUmpiresAnthemAndClosing(): void
    {
        $script = $this->render([], [], [
            ['name' => 'Edward Trudell', 'role' => 'Plate Umpire'],
            ['name' => 'Faith Hebert', 'role' => 'Field Umpire'],
        ]);

        // Sheet: "today's  game" (double space).
        self::assertStringContainsString(
            "\nThe umpires for today's game are Edward Trudell behind the plate, and Faith Hebert covering the bases.\n"
            . "\nAnd now please stand for the playing of the National Anthem.\n",
            $script
        );
        self::assertStringContainsString('Profanity, derogatory comments', $script);
        // The sportsmanship snippet reads the venue (sheet: "the Willamette Softball Field").
        self::assertStringContainsString('grounds for removal from Willamette Softball Field.', $script);
        self::assertStringEndsWith(
            "Willamette appreciates your cooperation in creating a safe and positive game environment.\n",
            $script
        );
        self::assertStringNotContainsString('{{', $script);
    }

    public function testEveningAtAnotherVenue(): void
    {
        $script = $this->render(['time_of_day' => '18:00:00', 'is_conference' => false, 'venue' => 'Wallace Park']);

        self::assertStringStartsWith(
            "Good evening and welcome to Wallace Park on the land of the Kalapuya for "
            . "tonight's non-conference Softball game",
            $script
        );
        self::assertStringContainsString('grounds for removal from Wallace Park.', $script);
    }

    public function testUmpirePositions(): void
    {
        self::assertSame('behind the plate', PlaceholderRegistry::umpirePosition('HP'));
        self::assertSame('behind the plate', PlaceholderRegistry::umpirePosition('Home Plate'));
        self::assertSame('at first base', PlaceholderRegistry::umpirePosition('1B'));
        self::assertSame('at third base', PlaceholderRegistry::umpirePosition('Third Base Umpire'));
        self::assertSame('covering the bases', PlaceholderRegistry::umpirePosition('Bases'));
        self::assertSame('', PlaceholderRegistry::umpirePosition('Umpire'));

        $context = ['officials' => [
            ['name' => 'Ana Ortiz', 'role' => 'Plate'],
            ['name' => 'Ben Cole', 'role' => '1B'],
            ['name' => 'Cy Dunn', 'role' => '3B'],
        ]];
        self::assertSame(
            'Ana Ortiz behind the plate, Ben Cole at first base, and Cy Dunn at third base',
            PlaceholderRegistry::resolve('officials.umpireList', $context)
        );
    }

    public function testBattingOrderChecks(): void
    {
        $messages = function (array $context): array {
            return array_map(function (array $check): string {
                return $check[0];
            }, ScriptChecks::forGame(self::game(), $context));
        };

        $complete = $messages(self::context([], [self::player(null, '1', 'Ann', '', 'P', 'Sr.', '')], [['name' => 'Ed', 'role' => 'Plate']]));
        self::assertContains('No batting order set for Lewis & Clark.', $complete);
        self::assertSame([], preg_grep('/Willamette/', $complete));

        $gappy = $messages(self::context([], [
            self::player(1, '1', 'Ann', '', 'P', 'Sr.', ''),
            self::player(1, '2', 'Bea', '', 'C', 'Sr.', ''),
            self::player(3, '3', 'Cat', '', 'SS', 'Sr.', ''),
        ]));
        self::assertContains("Ann and Bea share the 1st spot in Lewis & Clark's batting order.", $gappy);
        self::assertContains("Lewis & Clark's batting order has no 2nd, 4th, 5th, 6th, 7th, 8th, and 9th batter.", $gappy);
    }
}
