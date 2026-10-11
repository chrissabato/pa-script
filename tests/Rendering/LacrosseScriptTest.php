<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use App\Templates\DefaultTemplates;
use PHPUnit\Framework\TestCase;

/**
 * The Women's Lacrosse (home) template against the real "PA Script -
 * Lacrosse" sheet. Expected lines are the sheet's own rendered Script-tab
 * values (Willamette vs Pacific), except where the app deliberately
 * differs — those are called out next to each assertion.
 */
class LacrosseScriptTest extends TestCase
{
    /** Home starters as the sheet's Home tab lists them: number, name, phonetic, pos, year, hometown. */
    private const HOME_STARTERS = [
        ['4', 'Ella Lassahn', 'EL-uh LUH-sahn', 'D', 'So.', 'Salem, Oregon'],
        ['7', 'Jay Shiflett', 'JAY SHIFF-lit', 'D', 'Jr.', 'Glen Burnie, Maryland'],
        ['9', 'Danica Doskocil', 'DAN-i-kuh DOS-kuh-sill', 'M', 'Fy', 'Menifee, California'],
        ['10', 'Olivia Austin', 'oh-LIV-e-uh AWE-s-tin', 'GK', 'Jr.', 'San Diego, California'],
        ['13', 'Catie Mohr', 'KATE-ee MORE', 'D/M', 'Sr.', 'Portland, Oregon'],
        ['17', 'Claire Kisielnicki', 'CLAIRE KISS-el-nick-ee', 'A', 'Sr.', 'Manitou Springs, Colorado'],
        ['19', 'Alexandra Miller', 'ALEX-and-ruh MILL-er', 'A/M', 'Fy', 'Poway, California'],
        ['22', 'Priya Mistry', 'PREE-uh MISS-tree', 'A', 'Fy.', 'San Ramon, California'],
        ['23', 'Shelby Wilkinson', 'SHELL-bee WILK-in-sin', 'M', 'Fy.', 'Plymouth, Utah'],
        ['25', 'Alex Warfel', 'ALEX WAR-full', 'M', 'So.', 'Boise, Idaho'],
        ['26', 'Syd Shoemaker', 'SID SHOE-make-er', 'D', 'Jr.', 'Reno, Nevada'],
        ['27', 'Matthew Stout', '', 'D', 'Fy.', 'Long Beach, California'],
    ];

    /**
     * The sheet's Script!C20:C31 values for those starters, in jersey order
     * (the sheet reads them in its Setup tab's typed order, goalie first).
     */
    private const SHEET_HOME_LINES = [
        'At defense, a sophomore from Salem, Oregon, number 4, EL-uh LUH-sahn',
        'At defense, a junior from Glen Burnie, Maryland, number 7, JAY SHIFF-lit',
        'At midfield, a first year from Menifee, California, number 9, DAN-i-kuh DOS-kuh-sill',
        'At goalie, a junior from San Diego, California, number 10, oh-LIV-e-uh AWE-s-tin',
        'At defense, a senior from Portland, Oregon, number 13, KATE-ee MORE',
        'At attack, a senior from Manitou Springs, Colorado, number 17, CLAIRE KISS-el-nick-ee',
        'At attack, a first year from Poway, California, number 19, ALEX-and-ruh MILL-er',
        'At attack, a first year from San Ramon, California, number 22, PREE-uh MISS-tree',
        'At midfield, a first year from Plymouth, Utah, number 23, SHELL-bee WILK-in-sin',
        'At midfield, a sophomore from Boise, Idaho, number 25, ALEX WAR-full',
        'At defense, a junior from Reno, Nevada, number 26, SID SHOE-make-er',
        'At defense, a first year from Long Beach, California, number 27, Matthew Stout',
    ];

    private static function blocks(string $divisionLabel): array
    {
        return (new DefaultTemplates())->lacrosseHomeBlocks($divisionLabel);
    }

    private static function player(string $number, string $name, string $phonetic, string $pos, string $year, string $hometown, bool $captain = false): array
    {
        static $id = 0;

        return [
            'id' => ++$id, 'number' => $number, 'name' => $name, 'pronunciation' => $phonetic,
            'position' => $pos, 'year' => $year, 'hometown' => $hometown,
            'is_starter' => true, 'is_captain' => $captain, 'batting_order' => null,
        ];
    }

    private static function coach(int $id, string $name, string $title, ?string $pronoun = null, ?int $seasons = null): array
    {
        return ['id' => $id, 'name' => $name, 'pronunciation' => null, 'title' => $title, 'pronoun' => $pronoun, 'seasons' => $seasons, 'sort_order' => $id];
    }

    /** @param array<string, mixed> $gameOverrides */
    private function render(array $gameOverrides = [], array $awayPlayers = [], array $officials = [], string $divisionLabel = "Women's"): string
    {
        $game = array_merge([
            'id' => 1, 'sport_slug' => 'lacrosse', 'name_display' => 'pronunciation', // the sheets read only the guide
            'time_of_day' => '19:00:00', 'is_conference' => true,
            'venue' => null, 'special_announcement' => null,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'Pacific University', 'away_short_name' => 'Pacific', 'away_mascot' => 'Boxers',
        ], $gameOverrides);

        $homePlayers = array_map(function (array $row): array {
            return self::player(...$row);
        }, self::HOME_STARTERS);

        $context = GameContextBuilder::assemble($game, [
            'home' => ['players' => $homePlayers, 'coaches' => [
                self::coach(1, 'Sarah Lautenbach', 'Head Coach', 'her', 8),
                self::coach(2, 'Tia Krats', 'Assistant Coach'),
                self::coach(3, 'Lilly Stump', 'Assistant Coach'),
            ]],
            'away' => ['players' => $awayPlayers, 'coaches' => [
                self::coach(4, 'Brittany Hartmann', 'Head Coach', 'her', 8),
                self::coach(5, 'Alexa Jellison', 'Assistant Coach'),
                self::coach(6, 'Reba McLane', 'Assistant Coach'),
            ]],
        ], $officials);

        return (new TemplateRenderer())->render(self::blocks($divisionLabel), $context);
    }

    public function testWelcomeMatchesSheet(): void
    {
        $script = $this->render();

        // Sheet: "tonight'sNorthwest Conference" — its if() drops the space after "tonight's".
        self::assertStringStartsWith(
            "Good evening and welcome to Sparks Field on the land of the Kalapuya for "
            . "tonight's Northwest Conference Women's Lacrosse game between the Pacific University Boxers "
            . "and the Willamette University Bearcats. Before the game gets underway, let's meet the starters.\n\n"
            . "First for the visitors from Pacific\n",
            $script
        );
    }

    public function testHomeStarterLinesMatchSheet(): void
    {
        $script = $this->render();

        self::assertStringContainsString(
            "And now, the starting lineup for the Willamette University Bearcats\n"
            . implode("\n", self::SHEET_HOME_LINES) . "\n",
            $script
        );
    }

    public function testHeadCoachLines(): void
    {
        $script = $this->render();

        // Sheet: "... assisted by Tia Krats, Lilly Stump" (typed list, no period).
        self::assertStringContainsString(
            "Willamette's head coach is Sarah Lautenbach in her 8th season and is assisted by Tia Krats and Lilly Stump.\n",
            $script
        );
        self::assertStringContainsString(
            "Pacific's head coach is Brittany Hartmann in her 8th season and is assisted by Alexa Jellison and Reba McLane.\n",
            $script
        );
    }

    public function testAwayStartersCaptainAndPositions(): void
    {
        $script = $this->render([], [
            self::player('2', 'Ava Lee', '', 'M/A', 'Sr.', 'Hilo, Hawaii', true),
            self::player('3', 'Bo Kim', '', 'Att', 'Jr.', 'Tacoma, Wash.'),
        ]);

        self::assertStringContainsString(
            "First for the visitors from Pacific\n"
            . "A captain, at midfield, a senior from Hilo, Hawaii, number 2, Ava Lee\n"
            . "At attack, a junior from Tacoma, Washington, number 3, Bo Kim\n"
            . "Pacific's head coach is Brittany Hartmann",
            $script
        );
    }

    public function testOfficialsAnthemAndClosing(): void
    {
        $script = $this->render(
            ['time_of_day' => '13:00:00', 'is_conference' => false],
            [],
            [['name' => 'Ana Ortiz'], ['name' => 'Ben Cole'], ['name' => 'Cy Dunn']]
        );

        self::assertStringStartsWith(
            "Good afternoon and welcome to Sparks Field on the land of the Kalapuya for today's non-conference Women's Lacrosse game",
            $script
        );
        self::assertStringContainsString(
            "\nThe officials for today's game are Ana Ortiz, Ben Cole, and Cy Dunn.\n"
            . "\nAnd now for the playing of the National Anthem.\n",
            $script
        );
        // Sheet typos fixed: "Prfanity", "creating safe", "enviroment".
        self::assertStringContainsString('Profanity, derogatory comments', $script);
        self::assertStringContainsString('grounds for removal from Sparks Field.', $script);
        self::assertStringEndsWith(
            "Willamette appreciates your cooperation in creating a safe and positive game environment.\n",
            $script
        );
        self::assertStringNotContainsString('{{', $script);
    }

    public function testMensDivisionLabel(): void
    {
        self::assertStringContainsString("Northwest Conference Men's Lacrosse game", $this->render([], [], [], "Men's"));
    }
}
