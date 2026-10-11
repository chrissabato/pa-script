<?php

declare(strict_types=1);

namespace App\Templates;

/** Questions about a content_blocks tree. */
class Blocks
{
    /**
     * Whether the blocks use the named snippet anywhere, nested included.
     *
     * @param array<int, array<string, mixed>> $blocks
     */
    public static function usesSnippet(array $blocks, string $name): bool
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'snippet' && ($block['name'] ?? null) === $name) {
                return true;
            }
            $children = array_merge($block['template'] ?? [], $block['then'] ?? [], $block['else'] ?? []);
            if ($children !== [] && self::usesSnippet($children, $name)) {
                return true;
            }
        }

        return false;
    }
}
