<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * Renders a script_templates.content_blocks array (text/placeholder/loop
 * blocks) against a render context into plain text. Escaping for HTML output
 * happens at the view layer, not here.
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

            default:
                return '{{invalid-block}}';
        }
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
