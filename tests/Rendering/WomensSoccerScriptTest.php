<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The Women's Soccer (home) template against the real "PA Script - Women's
 * Soccer" sheet. Expected lines are the sheet's own rendered Script-tab
 * values (Willamette vs Lewis & Clark), except where the app deliberately
 * differs — those are called out next to each assertion.
 */
class WomensSoccerScriptTest extends TestCase
{
    /** Home starters as the sheet's Home tab lists them: number, name, phonetic, pos, year, hometown. */
    private const HOME_STARTERS = [
        ['1', 'Siera Edwards', 'SEE-era ED-werds', 'GK', 'Sr.', 'Portland, Oregon'],
        ['2', 'Natalie Rodriguez-De La Torre', 'NAT-uh-lee RAH-dree-gehz DAY LAH tore', 'F/M', 'Jr.', 'Burien, Washington'],
        ['5', 'Claire Warthen', 'kl-AIR WERTH-en', 'M', 'Jr.', 'Bremerton, Washington'],
        ['6', 'Stevie Bergstrom', 'STEE-vee BERG-strum', 'D', 'Sr.', 'Des Moines, Washington'],
        ['9', 'Erin Denney', 'AIR-in DEN-ee', 'F/M', 'Sr.', 'San Diego, California'],
        ['10', 'Elle Bachhuber', 'Ell BOCK-you-ber', 'M', 'So.', 'Henderson, Nevada'],
        ['11', 'Patricia Krepel', 'PUH-trish-uh KREP-el', 'D', 'So.', 'Bothell, Washington'],
        ['12', 'Bailey Watland', 'BAY-lee WATT-linn', 'M', 'So.', 'Silverdale, Washington'],
        ['13', 'Alysha Villelli', 'UH-leesh-uh VIL-el-ee', 'D', 'Sr.', 'Hayden Lake, Idaho'],
        ['21', 'Maddee Reid', 'MAD-ee REED', 'D', 'Jr.', 'Brentwood, California'],
        ['28', 'Amanda McArthur', 'uh-MAN-duh MIk-are-thur', 'F', 'So.', 'Independence, Oregon'],
    ];

    /** The sheet's Script!C19:C29 values for those starters. */
    private const SHEET_HOME_LINES = [
        'At goalie, a senior from Portland, Oregon, number 1, SEE-era ED-werds',
        'At forward, a junior from Burien, Washington, number 2, NAT-uh-lee RAH-dree-gehz DAY LAH tore',
        'At midfield, a junior from Bremerton, Washington, number 5, kl-AIR WERTH-en',
        'At defense, a senior from Des Moines, Washington, number 6, STEE-vee BERG-strum',
        'At forward, a senior from San Diego, California, number 9, AIR-in DEN-ee',
        'At midfield, a sophomore from Henderson, Nevada, number 10, Ell BOCK-you-ber',
        'At defense, a sophomore from Bothell, Washington, number 11, PUH-trish-uh KREP-el',
        'At midfield, a sophomore from Silverdale, Washington, number 12, BAY-lee WATT-linn',
        'At defense, a senior from Hayden Lake, Idaho, number 13, UH-leesh-uh VIL-el-ee',
        'At defense, a junior from Brentwood, California, number 21, MAD-ee REED',
        'At forward, a sophomore from Independence, Oregon, number 28, uh-MAN-duh MIk-are-thur',
    ];

    /** @var array<int, array<string, mixed>> */
    private $blocks;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../seeds/ScriptTemplatesSeeder.php';
        $method = new \ReflectionMethod(\ScriptTemplatesSeeder::class, 'soccerHomeBlocks');
        $method->setAccessible(true);
        $this->blocks = $method->invoke(new \ScriptTemplatesSeeder(), "Women's");
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
    private function render(array $gameOverrides = [], array $awayPlayers = [], array $officials = []): string
    {
        $game = array_merge([
            'id' => 1, 'sport_slug' => 'soccer', 'name_display' => 'pronunciation', // the sheets read only the guide
            'time_of_day' => '11:00:00', 'is_conference' => true,
            'venue' => null, 'special_announcement' => null,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'Lewis & Clark College', 'away_short_name' => 'Lewis & Clark', 'away_mascot' => 'River Otters',
        ], $gameOverrides);

        $homePlayers = array_map(function (array $row): array {
            return self::player(...$row);
        }, self::HOME_STARTERS);

        $context = GameContextBuilder::assemble($game, [
            'home' => ['players' => $homePlayers, 'coaches' => [
                self::coach(1, 'Lisa Ljubicic', 'Head Coach', 'her', 4),
                self::coach(2, 'Jeff Castagnola', 'Assistant Coach'),
                self::coach(3, 'Quinn Nottage', 'Assistant Coach'),
            ]],
            'away' => ['players' => $awayPlayers, 'coaches' => [
                // Real L&C staff titles: an assistant who is also another
                // team's head coach, listed first, must not be read as head.
                self::coach(5, 'Fraser Morrison', 'Assistant Coach / Head Men\'s Soccer Coach'),
                self::coach(4, 'Jim Tursi', "Head Women's Soccer Coach", 'his', 17),
                self::coach(6, 'Rebecca McCullough', 'Assistant Coach'),
                self::coach(7, 'Bill Irwin', 'Assistant Coach'),
            ]],
        ], $officials);

        return (new TemplateRenderer())->render($this->blocks, $context);
    }

    public function testWelcomeMatchesSheet(): void
    {
        $script = $this->render();

        self::assertStringStartsWith(
            "Good morning and welcome to Willamette University's Sparks Field on the land of the Kalapuya for "
            . "today's Northwest Conference Women's Soccer game between the Lewis & Clark College River Otters "
            . "and the Willamette University Bearcats. Before the game gets underway, let's meet the starters.\n\n"
            . "First for the visitors from Lewis & Clark\n",
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

        // Sheet: "... assisted by Jeff Castagnola, Quinn Nottage" (typed list, no period).
        self::assertStringContainsString(
            "Willamette's head coach is Lisa Ljubicic in her 4th season and is assisted by Jeff Castagnola and Quinn Nottage.\n",
            $script
        );
        self::assertStringContainsString(
            "Lewis & Clark's head coach is Jim Tursi in his 17th season and is assisted by "
            . "Fraser Morrison, Rebecca McCullough, and Bill Irwin.\n",
            $script
        );
    }

    public function testAwayStartersCaptainAndStateExpansion(): void
    {
        // The sheet expands away hometowns with FORMATSTATE ("Issaquah, Wash.").
        $script = $this->render([], [
            self::player('0', 'Brie Maker', '', 'GK', 'So.', 'Issaquah, Wash.', true),
            self::player('2', 'Kailani Pascua', '', 'M/F', 'Jr.', 'Kula, Hawaii'),
        ]);

        self::assertStringContainsString(
            "First for the visitors from Lewis & Clark\n"
            . "A captain, at goalie, a sophomore from Issaquah, Washington, number 0, Brie Maker\n"
            . "At midfield, a junior from Kula, Hawaii, number 2, Kailani Pascua\n"
            . "Lewis & Clark's head coach is Jim Tursi",
            $script
        );
    }

    public function testEveningNonConferenceVenueAndSpecialAnnouncement(): void
    {
        $script = $this->render([
            'time_of_day' => '19:00:00',
            'is_conference' => false,
            'venue' => 'McCulloch Stadium',
            'special_announcement' => 'Please welcome back our 2016 conference champions.',
            'closing_announcement' => 'Drive home safely.',
        ], [], [['name' => 'Ana Ortiz'], ['name' => 'Ben Cole'], ['name' => 'Cy Dunn']]);

        self::assertStringStartsWith(
            "Good evening and welcome to Willamette University's McCulloch Stadium on the land of the Kalapuya "
            . "for tonight's non-conference Women's Soccer game",
            $script
        );
        self::assertStringContainsString(
            "Ana Ortiz, Ben Cole, and Cy Dunn.\n\nPlease welcome back our 2016 conference champions.\n"
            . "\nAnd now the playing of the National Anthem.\n",
            $script
        );
        self::assertStringEndsWith("game environment.\n\nDrive home safely.\n", $script);
        self::assertStringContainsString("The officials for tonight's game are Ana Ortiz, Ben Cole, and Cy Dunn.\n", $script);
        self::assertStringContainsString('grounds for removal from McCulloch Stadium.', $script);
    }

    public function testClosingStatementTypoFixes(): void
    {
        $script = $this->render();

        self::assertStringContainsString("And now the playing of the National Anthem.\n", $script);
        self::assertStringContainsString('Profanity, derogatory comments', $script);
        self::assertStringEndsWith(
            "Willamette appreciates your cooperation in creating a safe and positive game environment.\n",
            $script
        );
        self::assertStringNotContainsString('{{', $script);
    }

    public function testNameDisplayBothShowsNameAndGuide(): void
    {
        $script = $this->render(['name_display' => 'both']);

        self::assertStringContainsString(
            "At goalie, a senior from Portland, Oregon, number 1, Siera Edwards (SEE-era ED-werds)\n",
            $script
        );
        $plain = $this->render(['name_display' => 'name']);
        self::assertStringContainsString("number 1, Siera Edwards\n", $plain);
        self::assertStringContainsString("Willamette's head coach is Lisa Ljubicic in her 4th season", $plain);
    }

    public function testNoAnthemDropsTheAnthemLine(): void
    {
        $script = $this->render(['has_anthem' => false]);

        self::assertStringNotContainsString('National Anthem', $script);
        self::assertStringContainsString("\n\nWillamette University, the Northwest Conference", $script);
        self::assertStringNotContainsString("\n\n\n", $script);
    }
}
