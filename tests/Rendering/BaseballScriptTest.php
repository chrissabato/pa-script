<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use App\Templates\DefaultTemplates;
use PHPUnit\Framework\TestCase;

/**
 * The Baseball (home) template against the real "PA Script - Baseball"
 * sheet's rendered Script tab (Willamette vs George Fox). It shares
 * Softball's script (SoftballScriptTest covers the shared parts); this
 * pins Baseball's venue and the non-batting starting pitcher (slot 10).
 */
class BaseballScriptTest extends TestCase
{
    /** Willamette's lineup: slot, number, name, year, hometown, playing today. Slot 10 = SP. */
    private const HOME_LINEUP = [
        [1, '1', 'Nate Hamburger', 'Sr.', 'Simi Valley, Calif.', 'center field'],
        [2, '22', 'Matthew Rienhardt', 'So.', 'San Jose, Calif.', '2nd base'],
        [3, '28', 'Carter Stoltz', 'Jr.', 'Davis, Calif.', 'catcher'],
        [4, '25', 'Trevor Sostman', 'So.', 'Van Nuys, Calif.', 'designated hitter'],
        [5, '21', 'Jason Smith', 'Sr.', 'Carmichael, Calif.', '1st base'],
        [6, '7', 'Olen Nofziger', 'Fy.', 'Redmond, Ore.', 'left field'],
        [7, '19', 'Michon Cole', 'Sr.', 'Oakley, Calif.', 'right field'],
        [8, '2', "Liam O'Connor", 'Jr.', 'Evergreen, Colo.', 'shortstop'],
        [9, '8', 'Cam Hammer', 'Fy.', 'Portland, Ore.', '3rd base'],
        [10, '16', 'Elliot Hsu', 'Jr.', 'Cupertino, Calif.', ''],
    ];

    /** The sheet's rendered lines for that lineup. */
    private const SHEET_HOME_LINES = [
        'Leading off at center field, a senior from Simi Valley, California, number 1, Nate Hamburger',
        'Batting 2nd at 2nd base, a sophomore from San Jose, California, number 22, Matthew Rienhardt',
        'Batting 3rd at catcher, a junior from Davis, California, number 28, Carter Stoltz',
        'Batting 4th at designated hitter, a sophomore from Van Nuys, California, number 25, Trevor Sostman',
        'Batting 5th at 1st base, a senior from Carmichael, California, number 21, Jason Smith',
        'Batting 6th at left field, a first year from Redmond, Oregon, number 7, Olen Nofziger',
        'Batting 7th at right field, a senior from Oakley, California, number 19, Michon Cole',
        "Batting 8th at shortstop, a junior from Evergreen, Colorado, number 2, Liam O'Connor",
        'Batting 9th at 3rd base, a first year from Portland, Oregon, number 8, Cam Hammer',
        'The starting pitcher, from Cupertino, California, number 16, Elliot Hsu',
    ];

    private function render(array $gameOverrides = []): string
    {
        $id = 0;
        $players = array_map(function (array $row) use (&$id): array {
            list($slot, $number, $name, $year, $hometown, $playing) = $row;

            return [
                'id' => ++$id, 'number' => $number, 'name' => $name, 'pronunciation' => null,
                'position' => 'P', 'year' => $year, 'hometown' => $hometown,
                'is_starter' => false, 'is_captain' => false, 'batting_order' => $slot, 'lineup_position' => $playing,
            ];
        }, self::HOME_LINEUP);

        $game = array_merge([
            'id' => 1, 'sport_slug' => 'baseball', 'division_slug' => null, 'sport_default_venue' => DefaultTemplates::VENUES['baseball'], 'uses_batting_order' => true, 'name_display' => 'pronunciation',
            'time_of_day' => '18:30:00', 'is_conference' => false,
            'venue' => null, 'special_announcement' => null,
            'home_school_name' => 'Willamette University', 'home_short_name' => 'Willamette', 'home_mascot' => 'Bearcats',
            'away_school_name' => 'George Fox University', 'away_short_name' => 'George Fox', 'away_mascot' => 'Bruins',
        ], $gameOverrides);
        $context = GameContextBuilder::assemble($game, [
            'home' => ['players' => $players, 'coaches' => [
                ['id' => 1, 'name' => 'Mike Caduto', 'pronunciation' => null, 'title' => 'Head Coach', 'pronoun' => 'his', 'seasons' => 3, 'sort_order' => 1],
            ]],
            'away' => ['players' => [], 'coaches' => []],
        ], [
            ['name' => 'Andrew Mervyn', 'role' => 'Plate'],
            ['name' => 'Alex Chavez', 'role' => '1B'],
            ['name' => 'Kevin Rice', 'role' => '3B'],
        ]);

        return (new TemplateRenderer((new DefaultTemplates())->snippets()))->render((new DefaultTemplates())->baseballHomeBlocks(), $context);
    }

    public function testWelcomeMatchesSheet(): void
    {
        // The shared Welcome snippet: the sheet's "ladies and gentlemen," is
        // gone and the team reads "Baseball". Sheet: "tonight'snon-conference".
        self::assertStringStartsWith(
            "Good evening and welcome to John Lewis Field at Spec Keene Stadium on the land of "
            . "the Kalapuya for tonight's non-conference Baseball game between the George Fox University Bruins and "
            . "the Willamette University Bearcats. Before the game gets underway, let's meet the starters.\n\n",
            $this->render()
        );
    }

    public function testBattingOrderAndStartingPitcherMatchSheet(): void
    {
        self::assertStringContainsString(
            "And now, the starting lineup for the Willamette University Bearcats\n"
            . implode("\n", self::SHEET_HOME_LINES) . "\n"
            . "Willamette's head coach is Mike Caduto in his 3rd season.\n",
            $this->render()
        );
    }

    public function testUmpiresAndClosing(): void
    {
        $script = $this->render();

        // Sheet: "... and Kevin Rice at third." — read as "at third base" like the other bases.
        self::assertStringContainsString(
            "The umpires for tonight's game are Andrew Mervyn behind the plate, Alex Chavez at first base, "
            . "and Kevin Rice at third base.\n\nAnd now please stand for the playing of the National Anthem.\n",
            $script
        );
        // The sportsmanship snippet reads the venue (sheet: "removal from the
        // Spec Keene Stadium").
        self::assertStringContainsString('grounds for removal from John Lewis Field at Spec Keene Stadium.', $script);
        self::assertStringNotContainsString('{{', $script);
    }
}
