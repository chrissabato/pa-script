<?php

declare(strict_types=1);

namespace Tests\Templates;

use App\Templates\DefaultTemplates;
use App\Templates\TemplateSyntax;
use App\Templates\TemplateSyntaxError;
use PHPUnit\Framework\TestCase;

class TemplateSyntaxTest extends TestCase
{
    /** @return array<string, array{0: array}> */
    public function defaultTemplates(): array
    {
        $cases = [];
        foreach ((new DefaultTemplates())->all() as $template) {
            $cases[$template['name']] = [$template['blocks']];
        }
        foreach ((new DefaultTemplates())->snippets() as $name => $blocks) {
            $cases['Snippet: ' . $name] = [$blocks];
        }

        return $cases;
    }

    /**
     * @dataProvider defaultTemplates
     */
    public function testDefaultTemplatesRoundTrip(array $blocks): void
    {
        $text = TemplateSyntax::toText($blocks);

        $this->assertSame(self::normalize($blocks), self::normalize(TemplateSyntax::parse($text)));
        $this->assertSame($text, TemplateSyntax::toText(TemplateSyntax::parse($text)));
    }

    public function testFriendlyNames(): void
    {
        $text = TemplateSyntax::toText((new DefaultTemplates())->soccerHomeBlocks());
        $welcome = TemplateSyntax::toText((new DefaultTemplates())->snippets()['Welcome']);

        $this->assertStringStartsWith("[snippet Welcome] Before the game gets underway, let's meet the starters.", $text);
        $this->assertStringContainsString('Good {part of day} and welcome to {venue} on the land', $welcome);
        $this->assertStringContainsString('{team} [if sport = volleyball]match[else]game[/if] between', $welcome);
        $this->assertStringContainsString("[each away starters][if captain]A captain, at [else]At [/if]{position}[if year], a {year}[/if]", $text);
        $this->assertStringContainsString("[if number], number {number}[/if], {name}\n[/each]", $text);
    }

    public function testParse(): void
    {
        $this->assertSame([
            ['type' => 'text', 'content' => 'Hi '],
            ['type' => 'placeholder', 'key' => 'away.mascot'],
            ['type' => 'when', 'key' => 'meta.isEvening', 'then' => [['type' => 'text', 'content' => ' tonight']], 'else' => []],
            ['type' => 'loop', 'over' => 'home.battingOrder', 'template' => [
                ['type' => 'when', 'key' => 'item.batting_order', 'equals' => 10, 'then' => [['type' => 'text', 'content' => 'Flex']], 'else' => [
                    ['type' => 'placeholder', 'key' => 'item.battingOrderLabel'],
                ]],
            ]],
            ['type' => 'placeholder', 'key' => 'meta.date'],
        ], TemplateSyntax::parse(
            "Hi {Away  Mascot}[if evening] tonight[/if][each home batting order][if batting slot = 10]Flex[else]{batting slot}[/if][/each]{meta.date}"
        ));
    }

    public function testSnippets(): void
    {
        $blocks = TemplateSyntax::parse("[snippet welcome]\n[[snippet x]", ['Welcome', 'Sportsmanship statement']);

        $this->assertSame([
            ['type' => 'snippet', 'name' => 'Welcome'],
            ['type' => 'text', 'content' => "\n[snippet x]"],
        ], $blocks);
        $this->assertSame("[snippet Welcome]\n[[snippet x]", TemplateSyntax::toText($blocks));

        try {
            TemplateSyntax::parse("a\n[snippet Welcom]", ['Welcome']);
            $this->fail('Expected a syntax error');
        } catch (TemplateSyntaxError $e) {
            $this->assertSame(2, $e->getTemplateLine());
            $this->assertStringContainsString('no snippet called "Welcom"', $e->getMessage());
        }
    }

    public function testLiteralBracketsAndBraces(): void
    {
        $blocks = [['type' => 'text', 'content' => '[START Joker] {x} [if y] a[[b']];
        $text = TemplateSyntax::toText($blocks);

        $this->assertSame('[START Joker] {{x} [[if y] a[[[b', $text);
        $this->assertSame($blocks, TemplateSyntax::parse($text));
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public function errors(): array
    {
        return [
            'unknown field' => ["Hi\n{venu}", 2, '"venu"'],
            'item field outside a list' => ['{position}', 1, 'only works inside an [each]'],
            'unclosed if' => ["a\n[if venue]x", 2, 'never closed'],
            'stray close' => ['x[/each]', 1, 'without a matching'],
            'crossed tags' => ["[each home starters]\n[if captain]x[/each]", 2, 'needs [/if] first'],
            'second else' => ['[if venue]a[else]b[else]c[/if]', 1, '[else]'],
            'lone brace' => ['a { b', 1, '{{'],
            'not a list' => ['[each venue][/each]', 1, 'a list'],
        ];
    }

    /**
     * @dataProvider errors
     */
    public function testErrors(string $text, int $line, string $message): void
    {
        try {
            TemplateSyntax::parse($text);
            $this->fail('Expected a syntax error');
        } catch (TemplateSyntaxError $e) {
            $this->assertSame($line, $e->getTemplateLine());
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    /**
     * Blocks as the parser produces them: adjacent text merged, empty text
     * dropped, `when` always with both branches.
     */
    private static function normalize(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'text') {
                if ($block['content'] === '') {
                    continue;
                }
                $last = count($out) - 1;
                if ($last >= 0 && $out[$last]['type'] === 'text') {
                    $out[$last]['content'] .= $block['content'];
                    continue;
                }
            } elseif ($block['type'] === 'when') {
                $when = ['type' => 'when', 'key' => $block['key']];
                if (array_key_exists('equals', $block)) {
                    $when['equals'] = $block['equals'];
                }
                $when['then'] = self::normalize($block['then'] ?? []);
                $when['else'] = self::normalize($block['else'] ?? []);
                $block = $when;
            } elseif ($block['type'] === 'loop') {
                $block['template'] = self::normalize($block['template']);
            }
            $out[] = $block;
        }

        return $out;
    }
}
