<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use App\Templates\DefaultTemplates;
use PHPUnit\Framework\TestCase;

/**
 * The Volleyball template against the real "PA Script - Volleyball" sheet
 * (Willamette vs Lewis & Clark). Reserve lines are the sheet's own Script
 * values — except its #10 row, which is a #REF! in the sheet itself and so
 * printed out of order there.
 */
class VolleyballScriptTest extends TestCase
{
    /** Home tab rows: number, name, pronunciation, position, year, hometown. */
    private const HOME = [
        ['1', 'Dream Takemoto', 'DREAM TAH-keh-moh-toh', 'DS/L', 'Jr.', 'Waipahu, Hawaii'],
        ['2', 'Lea Prunk', 'LAY-uh PUNK', 'DS/L', 'Fy.', 'Redmond, Washington'],
        ['3', 'Lylah Worsley', 'LIE-luh WERE-slee', 'S', 'So.', 'Ewa Beach, Hawaii'],
        ['4', 'Brianna Everett', 'BREE-on-uh EV-rit', 'S', 'Fy.', 'Bend, Oregon'],
        ['5', 'Maddy Brewster', '', 'OPP', 'Sr.', 'Lakeport, California'],
        ['6', 'Callie Chong', '', 'DS/OH', 'Fy.', 'Hilo, Hawaii'],
        ['7', 'Lexie Scott', 'LEX-ee SCOTT', 'OH/OPP', 'Jr.', 'Seabeck, Washington'],
        ['8', 'Tai Sorenson', 'TIE SORE-in-sin', 'DS/L', 'So.', 'Honolulu, Hawaii'],
        ['9', 'Leina Chu', 'LAY-na CHEW', 'S', 'Jr.', 'Kaneohe, Hawaii'],
        ['12', 'Tessa Reed', 'TESS-uh REED', 'MB', 'Fy.', 'Sammamish, Washington'],
        ['14', 'Emalia Freitas', 'ema-LEE-uh FRAY-tis', 'MB', 'Jr.', 'Laie, Hawaii'],
        ['15', 'Abbi Bailey', 'ABBY BAIL-ee', 'OH/OPP', 'Fy.', 'Gresham, Oregon'],
        ['16', 'Cora Sorem', 'CORE-uh SORE-uhm', 'MH/OPP', 'So.', 'Salem, Oregon'],
    ];

    /** @var array<int, array<string, mixed>> */
    private $blocks;

    protected function setUp(): void
    {
        $this->blocks = (new DefaultTemplates())->volleyballBlocks();
    }

    private static function player(array $row, bool $starter = false): array
    {
        static $id = 0;
        [$number, $name, $pronunciation, $position, $year, $hometown] = $row;

        return [
            'id' => ++$id, 'number' => $number, 'name' => $name, 'pronunciation' => $pronunciation,
            'position' => $position, 'year' => $year, 'hometown' => $hometown,
            'is_starter' => $starter, 'is_captain' => false, 'batting_order' => null,
        ];
    }

    private static function coach(int $id, string $name, string $title, ?string $pronoun = null, ?int $seasons = null): array
    {
        return ['id' => $id, 'name' => $name, 'pronunciation' => null, 'title' => $title, 'pronoun' => $pronoun, 'seasons' => $seasons, 'sort_order' => $id];
    }

    private function render(array $homePlayers, array $awayPlayers = [], array $officials = [], bool $hasAnthem = true): string
    {
        $game = [
            'id' => 1, 'sport_slug' => 'volleyball', 'name_display' => 'pronunciation', // the sheets read only the guide
            'time_of_day' => '11:00:00', 'is_conference' => true,
            'venue' => null, 'special_announcement' => 'Welcome, Class of 1976.', 'has_anthem' => $hasAnthem,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'Lewis & Clark College', 'away_short_name' => 'Lewis & Clark', 'away_mascot' => 'River Otters',
        ];
        $context = GameContextBuilder::assemble($game, [
            'home' => ['players' => $homePlayers, 'coaches' => [
                self::coach(1, 'Lily Hallock', 'Head Coach', 'her', 5),
                self::coach(2, 'Kinley Hickok', 'Assistant Coach'),
                self::coach(3, 'Dani Queja', 'Assistant Coach'),
            ]],
            'away' => ['players' => $awayPlayers, 'coaches' => [
                self::coach(4, 'Erin Labasan', 'Head Volleyball Coach', 'her', 5),
                self::coach(5, 'Hannah Kimler', 'Assistant Coach'),
            ]],
        ], $officials);

        return (new TemplateRenderer())->render($this->blocks, $context);
    }

    public function testWelcomeAnthemAndIntro(): void
    {
        $script = $this->render([]);

        self::assertStringStartsWith(
            "Good morning and welcome to Willamette University's Cone Field House on the land of the Kalapuya for "
            . "today's Northwest Conference Volleyball match between the Lewis & Clark College River Otters and your "
            . "Willamette University Bearcats.\n\nWelcome, Class of 1976.\n\nAnd now for the playing of the National Anthem.\n\n"
            . "Before the match gets underway, let's meet the teams.\n\n",
            $script
        );
    }

    public function testHomeReservesMatchSheet(): void
    {
        $script = $this->render(array_map(function (array $row): array {
            return self::player($row);
        }, self::HOME));

        self::assertStringContainsString(
            "[start Joker and the Thief]\nNow let's meet your Willamette University Bearcats. First the reserves\n"
            . "Number 1, DREAM TAH-keh-moh-toh\nNumber 2, LAY-uh PUNK\nNumber 3, LIE-luh WERE-slee\n"
            . "Number 4, BREE-on-uh EV-rit\nNumber 5, Maddy Brewster\nNumber 6, Callie Chong\nNumber 7, LEX-ee SCOTT\n"
            . "Number 8, TIE SORE-in-sin\nNumber 9, LAY-na CHEW\nNumber 12, TESS-uh REED\n"
            . "Number 14, ema-LEE-uh FRAY-tis\nNumber 15, ABBY BAIL-ee\nNumber 16, CORE-uh SORE-uhm\n"
            . "\nNow the starters for the Bearcats.\n",
            $script
        );
        self::assertStringContainsString(
            "Willamette's head coach is Lily Hallock in her 5th season and is assisted by Kinley Hickok and Dani Queja.\n",
            $script
        );
    }

    public function testStartersSpokenPositionsWithLiberoLast(): void
    {
        // Ticked as the libero (listed position DS/L): read "At libero" and last.
        $libero = self::player(self::HOME[0], true);
        $libero['is_libero'] = true;
        $script = $this->render([
            $libero,
            self::player(self::HOME[2], true),   // S
            self::player(self::HOME[6], true),   // OH/OPP
            self::player(self::HOME[9], true),   // MB
            self::player(self::HOME[12], true),  // MH/OPP
        ]);

        self::assertStringContainsString(
            "Now the starters for the Bearcats.\n"
            . "At setter, a sophomore from Ewa Beach, Hawaii, number 3, LIE-luh WERE-slee\n"
            . "At outside, a junior from Seabeck, Washington, number 7, LEX-ee SCOTT\n"
            . "At middle blocker, a first year from Sammamish, Washington, number 12, TESS-uh REED\n"
            . "At middle hitter, a sophomore from Salem, Oregon, number 16, CORE-uh SORE-uhm\n"
            . "At libero, a junior from Waipahu, Hawaii, number 1, DREAM TAH-keh-moh-toh\n",
            $script
        );
        // No reserves: the "First the reserves" lead-in is dropped.
        self::assertStringContainsString("Now let's meet your Willamette University Bearcats.\n", $script);
    }

    public function testVisitorsAndOfficialsByRole(): void
    {
        $script = $this->render([], [
            self::player(['1', 'Kenzie Edwards', '', 'S', 'Sr.', 'Wilsonville, Ore.']),
            self::player(['2', 'Gracie De Fina', 'Gracie deh-FEE–nuh', 'DS', 'Fy.', 'Napa, Calif.']),
        ], [
            ['name' => 'Ana Ortiz', 'role' => 'Referee'], ['name' => 'Ben Cole', 'role' => 'Referee'],
            ['name' => 'Cy Dunn', 'role' => 'Linesman'], ['name' => 'Di Ell', 'role' => 'Linesman'],
        ]);

        self::assertStringContainsString(
            "First, the reserves for the visitors from Lewis & Clark\n"
            . "Number 1, Kenzie Edwards\nNumber 2, Gracie deh-FEE–nuh\n"
            . "\nAnd now the starters for the River Otters\n"
            . "Lewis & Clark's head coach is Erin Labasan in her 5th season and is assisted by Hannah Kimler.\n",
            $script
        );
        self::assertStringContainsString(
            "The officials for today's game are referees Ana Ortiz and Ben Cole, and linesmen Cy Dunn and Di Ell.\n",
            $script
        );
        self::assertStringEndsWith("creating a safe and positive game environment.\n", $script);
        self::assertStringNotContainsString('{{', $script);
    }

    public function testListedPositionLAlsoReadsAsLibero(): void
    {
        $script = $this->render([
            self::player(['1', 'Dream Takemoto', 'DREAM TAH-keh-moh-toh', 'L', 'Jr.', 'Waipahu, Hawaii'], true),
            self::player(self::HOME[2], true),
        ]);

        self::assertStringContainsString(
            "number 3, LIE-luh WERE-slee\nAt libero, a junior from Waipahu, Hawaii, number 1, DREAM TAH-keh-moh-toh\n",
            $script
        );
    }

    public function testNoAnthemDropsTheAnthemLine(): void
    {
        self::assertStringContainsString(
            "your Willamette University Bearcats.\n\nWelcome, Class of 1976.\n\nBefore the match gets underway, let's meet the teams.\n\n",
            $this->render([], [], [], false)
        );
    }
}
