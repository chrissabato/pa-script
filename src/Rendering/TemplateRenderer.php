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
