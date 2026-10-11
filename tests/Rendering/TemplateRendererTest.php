<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    /** @var TemplateRenderer */
    private $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer();
    }

    public function testTextBlockRendersVerbatim(): void
    {
        $blocks = [['type' => 'text', 'content' => 'Welcome to Bearcat Field.']];

        self::assertSame('Welcome to Bearcat Field.', $this->renderer->render($blocks, []));
    }

    public function testPlaceholderResolvesDotPath(): void
    {
        $blocks = [['type' => 'placeholder', 'key' => 'home.schoolName']];
        $context = ['home' => ['schoolName' => 'Willamette University']];

        self::assertSame('Willamette University', $this->renderer->render($blocks, $context));
    }

    public function testMissingPlaceholderIsVisiblyFlaggedNotBlank(): void
    {
        $blocks = [['type' => 'placeholder', 'key' => 'home.schoolName']];

        self::assertSame('{{missing:home.schoolName}}', $this->renderer->render($blocks, []));
    }

    public function testLoopOverStartersExpandsEachPlayer(): void
    {
        $blocks = [
            [
                'type' => 'loop',
                'over' => 'home.starters',
                'template' => [
                    ['type' => 'text', 'content' => 'At '],
                    ['type' => 'placeholder', 'key' => 'item.position'],
                    ['type' => 'text', 'content' => ', number '],
                    ['type' => 'placeholder', 'key' => 'item.number'],
                    ['type' => 'text', 'content' => ', '],
                    ['type' => 'placeholder', 'key' => 'item.name'],
                    ['type' => 'text', 'content' => ".\n"],
                ],
            ],
        ];

        $context = [
            'home' => [
                'starters' => [
                    ['position' => 'Forward', 'number' => '9', 'name' => 'Jane Doe'],
                    ['position' => 'Goalkeeper', 'number' => '1', 'name' => 'Amy Smith'],
                ],
            ],
        ];

        $expected = "At Forward, number 9, Jane Doe.\nAt Goalkeeper, number 1, Amy Smith.\n";

        self::assertSame($expected, $this->renderer->render($blocks, $context));
    }

    public function testLoopOverBattingOrderComputesOrdinalLabelFromInteger(): void
    {
        $blocks = [
            [
                'type' => 'loop',
                'over' => 'home.battingOrder',
                'template' => [
                    ['type' => 'placeholder', 'key' => 'item.battingOrderLabel'],
                    ['type' => 'text', 'content' => ' at '],
                    ['type' => 'placeholder', 'key' => 'item.position'],
                    ['type' => 'text', 'content' => ', '],
                    ['type' => 'placeholder', 'key' => 'item.name'],
                    ['type' => 'text', 'content' => ".\n"],
                ],
            ],
        ];

        $context = [
            'home' => [
                'battingOrder' => [
                    ['batting_order' => 1, 'position' => 'shortstop', 'name' => 'Sam Lee'],
                    ['batting_order' => 2, 'position' => 'catcher', 'name' => 'Pat Kim'],
                    ['batting_order' => 3, 'position' => 'first base', 'name' => 'Jo Ray'],
                ],
            ],
        ];

        $expected = "Leading off at shortstop, Sam Lee.\n"
            . "Batting 2nd at catcher, Pat Kim.\n"
            . "Batting 3rd at first base, Jo Ray.\n";

        self::assertSame($expected, $this->renderer->render($blocks, $context));
    }

    public function testOfficialsJoinedListPunctuatesCorrectly(): void
    {
        $blocks = [['type' => 'placeholder', 'key' => 'officials.joinedList']];

        self::assertSame('Alex', $this->renderer->render($blocks, [
            'officials' => [['name' => 'Alex']],
        ]));

        self::assertSame('Alex and Bo', $this->renderer->render($blocks, [
            'officials' => [['name' => 'Alex'], ['name' => 'Bo']],
        ]));

        self::assertSame('Alex, Bo, and Cam', $this->renderer->render($blocks, [
            'officials' => [['name' => 'Alex'], ['name' => 'Bo'], ['name' => 'Cam']],
        ]));
    }

    public function testLoopOverReservesRendersPlainNumberNameLine(): void
    {
        $blocks = [
            [
                'type' => 'loop',
                'over' => 'home.reserves',
                'template' => [
                    ['type' => 'text', 'content' => 'Number '],
                    ['type' => 'placeholder', 'key' => 'item.number'],
                    ['type' => 'text', 'content' => ', '],
                    ['type' => 'placeholder', 'key' => 'item.name'],
                    ['type' => 'text', 'content' => ".\n"],
                ],
            ],
        ];

        $context = [
            'home' => [
                'reserves' => [
                    ['number' => '15', 'name' => 'Erin Cole'],
                ],
            ],
        ];

        self::assertSame("Number 15, Erin Cole.\n", $this->renderer->render($blocks, $context));
    }

    public function testLoopOverMissingKeyRendersEmptyRatherThanError(): void
    {
        $blocks = [
            [
                'type' => 'loop',
                'over' => 'away.reserves',
                'template' => [['type' => 'placeholder', 'key' => 'item.name']],
            ],
        ];

        self::assertSame('', $this->renderer->render($blocks, ['home' => []]));
    }

    public function testWhenBlockPicksBranchByTruthinessOrEquality(): void
    {
        $blocks = [
            ['type' => 'when', 'key' => 'item.is_captain', 'then' => [['type' => 'text', 'content' => 'A captain, at ']],
                'else' => [['type' => 'text', 'content' => 'At ']]],
            ['type' => 'when', 'key' => 'item.number', 'then' => [['type' => 'text', 'content' => '#']]],
            ['type' => 'when', 'key' => 'meta.partOfDay', 'equals' => 'evening',
                'then' => [['type' => 'text', 'content' => " tonight"]], 'else' => [['type' => 'text', 'content' => ' today']]],
        ];

        self::assertSame('A captain, at # today', $this->renderer->render($blocks, [
            'item' => ['is_captain' => true, 'number' => 0],
            'meta' => ['partOfDay' => 'morning'],
        ]));
        // false/missing take the else branch; jersey number 0 still counts as set.
        self::assertSame('At  tonight', $this->renderer->render($blocks, [
            'item' => ['is_captain' => false],
            'meta' => ['partOfDay' => 'evening'],
        ]));
    }

    public function testSnippetRendersInPlaceWithTheSameContext(): void
    {
        $renderer = new TemplateRenderer([
            'Welcome' => [
                ['type' => 'text', 'content' => 'Welcome to '],
                ['type' => 'placeholder', 'key' => 'meta.venue'],
                ['type' => 'snippet', 'name' => 'Period'],
            ],
            'Period' => [['type' => 'text', 'content' => '.']],
        ]);

        $this->assertSame('Welcome to Sparks Field. Hi', $renderer->render([
            ['type' => 'snippet', 'name' => 'Welcome'],
            ['type' => 'text', 'content' => ' Hi'],
        ], ['meta' => ['venue' => 'Sparks Field']]));
    }

    public function testMissingAndSelfReferencingSnippetsDontBreakTheScript(): void
    {
        $renderer = new TemplateRenderer(['Loop' => [['type' => 'text', 'content' => 'x'], ['type' => 'snippet', 'name' => 'Loop']]]);

        $this->assertSame('{{missing-snippet:Nope}}', $renderer->render([['type' => 'snippet', 'name' => 'Nope']], []));
        $this->assertSame('xxxxx{{snippet-loop:Loop}}', $renderer->render([['type' => 'snippet', 'name' => 'Loop']], []));
    }
}
