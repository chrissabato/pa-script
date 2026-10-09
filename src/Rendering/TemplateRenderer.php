<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * Renders a script_templates.content_blocks array against a render context
 * into plain text. Escaping for HTML output happens at the view layer.
 *
 * Block types:
 * - {type: text, content}
 * - {type: placeholder, key}
 * - {type: loop, over, template: [...]} — once per item, as `item`
 * - {type: when, key, equals?, then: [...], else?: [...]} — `then` when the
 *   value at `key` is set (not null/''/false/[] — 0 counts as set, e.g.
 *   jersey number 0) or, with `equals`, matches
 *   it; otherwise `else`. Covers the sheets' if(...) wording switches
 *   ("A captain, at" vs "At", "tonight's" vs "today's", ...).
 */
class TemplateRenderer
{
    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $context
     */
    public function render(array $blocks, array $context): string
    {
        $output = '';

        foreach ($blocks as $block) {
            $output .= $this->renderBlock($block, $context);
        }

        return $output;
    }

    /**
     * The text a template reads when `key` is blank — the `else` of its
     * `when key` block, e.g. a home template's usual venue ("Sparks Field").
     * Null when the template has no such fallback (or it isn't plain text).
     *
     * @param array<int, array<string, mixed>> $blocks
     */
    public static function fallbackText(array $blocks, string $key): ?string
    {
        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            if ($type === 'when' && ($block['key'] ?? null) === $key && !array_key_exists('equals', $block)) {
                $text = '';
                foreach ($block['else'] ?? [] as $else) {
                    if (($else['type'] ?? null) !== 'text') {
                        return null;
                    }
                    $text .= (string) ($else['content'] ?? '');
                }

                return $text !== '' ? $text : null;
            }
            $children = $type === 'loop'
                ? ($block['template'] ?? [])
                : array_merge($block['then'] ?? [], $block['else'] ?? []);
            $found = self::fallbackText($children, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    private function renderBlock(array $block, array $context): string
    {
        switch ($block['type'] ?? null) {
            case 'text':
                return (string) ($block['content'] ?? '');

            case 'placeholder':
                return PlaceholderRegistry::resolve($block['key'], $context);

            case 'loop':
                return $this->renderLoop($block, $context);

            case 'when':
                return $this->render($this->whenBranch($block, $context), $context);

            default:
                return '{{invalid-block}}';
        }
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function whenBranch(array $block, array $context): array
    {
        $value = PlaceholderRegistry::getPath($context, (string) ($block['key'] ?? ''));

        $matches = array_key_exists('equals', $block)
            ? $value !== null && (string) $value === (string) $block['equals']
            : !in_array($value, [null, '', false, []], true);

        return $matches ? ($block['then'] ?? []) : ($block['else'] ?? []);
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    private function renderLoop(array $block, array $context): string
    {
        $items = PlaceholderRegistry::getPath($context, $block['over']);

        if (!is_array($items)) {
            return '';
        }

        $template = $block['template'] ?? [];
        $output = '';

        foreach ($items as $item) {
            $itemContext = $context;
            $itemContext['item'] = $item;
            $output .= $this->render($template, $itemContext);
        }

        return $output;
    }
}
