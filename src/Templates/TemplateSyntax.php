<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * The template editor's text form of script_templates.content_blocks, both
 * ways:
 *
 *   Good {part of day} and welcome to [if venue]{venue}[else]Sparks Field[/if]
 *   [each away starters][if captain]A captain, at [else]At [/if]{position}, {name}
 *   [/each]
 *
 * - {name} — a placeholder block; `name` is a FieldCatalog name or a raw
 *   context key with a dot (`{meta.date}`).
 * - [if name]…[else]…[/if] — a `when` block (else optional);
 *   [if name = value] adds `equals`.
 * - [each name]…[/each] — a `loop` block.
 * - Everything else is text, line breaks included. Other bracketed text
 *   ("[START Joker and the Thief]") stays text; `{{` and `[[` are a literal
 *   `{` and `[` (only needed before something that would read as a tag).
 *
 * toText() then parse() gives back blocks that render identically (adjacent
 * text blocks merge).
 */
class TemplateSyntax
{
    private const TOKEN = '/(\[\[|\{\{|\{[^{}\n]*\}|\[(?:if\s[^\]\n]*|else|\/if|each\s[^\]\n]*|\/each)\])/i';

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public static function toText(array $blocks, ?string $itemKind = null): string
    {
        $text = '';
        foreach ($blocks as $block) {
            switch ($block['type'] ?? null) {
                case 'text':
                    $text .= self::escape((string) ($block['content'] ?? ''));
                    break;
                case 'placeholder':
                    $text .= '{' . self::name((string) $block['key'], 'value', $itemKind) . '}';
                    break;
                case 'when':
                    $condition = self::name((string) $block['key'], 'test', $itemKind);
                    if (array_key_exists('equals', $block)) {
                        $condition .= ' = ' . $block['equals'];
                    }
                    $text .= '[if ' . $condition . ']' . self::toText($block['then'] ?? [], $itemKind);
                    if (($block['else'] ?? []) !== []) {
                        $text .= '[else]' . self::toText($block['else'], $itemKind);
                    }
                    $text .= '[/if]';
                    break;
                case 'loop':
                    $over = (string) $block['over'];
                    $text .= '[each ' . self::name($over, 'list', $itemKind) . ']'
                        . self::toText($block['template'] ?? [], FieldCatalog::itemKindOf($over))
                        . '[/each]';
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown block type: ' . json_encode($block));
            }
        }

        return $text;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws TemplateSyntaxError
     */
    public static function parse(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $parts = preg_split(self::TOKEN, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);

        // Open [if]/[each] tags, innermost last; the root collects the result.
        $stack = [['kind' => 'root', 'blocks' => [], 'itemKind' => null]];

        foreach ($parts as $i => [$part, $offset]) {
            if ($part === '') {
                continue;
            }
            $top = count($stack) - 1;
            $itemKind = $stack[$top]['itemKind'];
            $line = substr_count(substr($text, 0, $offset), "\n") + 1;

            if ($i % 2 === 0) {
                if (strpos($part, '{') !== false) {
                    throw new TemplateSyntaxError('A "{" with no matching "}" — write {{ for a literal {.', $line);
                }
                self::appendText($stack[$top]['blocks'], $part);
                continue;
            }

            if ($part === '[[' || $part === '{{') {
                self::appendText($stack[$top]['blocks'], $part[0]);
                continue;
            }

            if ($part[0] === '{') {
                $stack[$top]['blocks'][] = ['type' => 'placeholder', 'key' => self::key(substr($part, 1, -1), 'value', $itemKind, $line, $part)];
                continue;
            }

            $tag = strtolower(trim(substr($part, 1, -1)));
            if (strpos($tag, 'if ') === 0) {
                $condition = trim(substr($part, 3, -1));
                $block = ['type' => 'when'];
                if (preg_match('/^(.*?)\s*=\s*(.*)$/', $condition, $m)) {
                    $block['key'] = self::key($m[1], 'test', $itemKind, $line, $part);
                    $block['equals'] = ctype_digit($m[2]) ? (int) $m[2] : $m[2];
                } else {
                    $block['key'] = self::key($condition, 'test', $itemKind, $line, $part);
                }
                $block['then'] = [];
                $block['else'] = [];
                $stack[] = ['kind' => 'if', 'block' => $block, 'blocks' => [], 'inElse' => false, 'itemKind' => $itemKind, 'line' => $line];
            } elseif ($tag === 'else') {
                if ($stack[$top]['kind'] !== 'if' || $stack[$top]['inElse']) {
                    throw new TemplateSyntaxError('[else] without an [if] to belong to.', $line);
                }
                $stack[$top]['block']['then'] = $stack[$top]['blocks'];
                $stack[$top]['blocks'] = [];
                $stack[$top]['inElse'] = true;
            } elseif ($tag === '/if') {
                if ($stack[$top]['kind'] !== 'if') {
                    throw new TemplateSyntaxError(self::closeMismatch('[/if]', $stack[$top]), $line);
                }
                $frame = array_pop($stack);
                $frame['block'][$frame['inElse'] ? 'else' : 'then'] = $frame['blocks'];
                $stack[count($stack) - 1]['blocks'][] = $frame['block'];
            } elseif (strpos($tag, 'each ') === 0) {
                $over = self::key(trim(substr($part, 5, -1)), 'list', $itemKind, $line, $part);
                $stack[] = [
                    'kind' => 'each',
                    'block' => ['type' => 'loop', 'over' => $over, 'template' => []],
                    'blocks' => [],
                    'itemKind' => FieldCatalog::itemKindOf($over),
                    'line' => $line,
                ];
            } else { // [/each]
                if ($stack[$top]['kind'] !== 'each') {
                    throw new TemplateSyntaxError(self::closeMismatch('[/each]', $stack[$top]), $line);
                }
                $frame = array_pop($stack);
                $frame['block']['template'] = $frame['blocks'];
                $stack[count($stack) - 1]['blocks'][] = $frame['block'];
            }
        }

        if (count($stack) > 1) {
            $open = $stack[count($stack) - 1];
            throw new TemplateSyntaxError(
                'This [' . $open['kind'] . '] is never closed — add [/' . $open['kind'] . '].',
                $open['line']
            );
        }

        return $stack[0]['blocks'];
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private static function appendText(array &$blocks, string $text): void
    {
        $last = count($blocks) - 1;
        if ($last >= 0 && $blocks[$last]['type'] === 'text') {
            $blocks[$last]['content'] .= $text;
        } else {
            $blocks[] = ['type' => 'text', 'content' => $text];
        }
    }

    /** @throws TemplateSyntaxError */
    private static function key(string $name, string $role, ?string $itemKind, int $line, string $tag): string
    {
        $key = FieldCatalog::keyFor($name, $role, $itemKind);
        if ($key !== null) {
            return $key;
        }
        $name = trim($name);
        if (preg_match('/^[A-Za-z_]\w*(\.\w+)+$/', $name)) {
            return $name;
        }
        $where = ['value' => 'a field', 'test' => 'something to check with [if]', 'list' => 'a list for [each]'][$role];
        $hint = $itemKind === null && FieldCatalog::keyFor($name, $role, 'player') !== null
            ? ' — it only works inside an [each] list'
            : '';

        throw new TemplateSyntaxError('"' . $name . '" in ' . $tag . ' isn\'t ' . $where . $hint . '.', $line);
    }

    private static function name(string $key, string $role, ?string $itemKind): string
    {
        $label = FieldCatalog::labelFor($key, $role, $itemKind);

        return $label !== null ? $label : $key;
    }

    /** @param array<string, mixed> $frame */
    private static function closeMismatch(string $close, array $frame): string
    {
        return $frame['kind'] === 'root'
            ? $close . ' without a matching opening tag.'
            : $close . ' found, but the [' . $frame['kind'] . '] on line ' . $frame['line'] . ' needs [/' . $frame['kind'] . '] first.';
    }

    /**
     * Text as the editor shows it: `{` always doubled, `[` doubled where it
     * would otherwise start a tag (or a `[[`).
     */
    private static function escape(string $text): string
    {
        $text = str_replace('{', '{{', $text);

        return (string) preg_replace('/\[(?=\[|if\s|else\]|\/if\]|each\s|\/each\])/i', '[[', $text);
    }
}
