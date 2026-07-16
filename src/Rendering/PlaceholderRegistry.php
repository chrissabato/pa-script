<?php

declare(strict_types=1);

namespace App\Rendering;

/**
 * Resolves a placeholder key against a render context array.
 *
 * Most keys are plain dot-paths into $context (e.g. "home.schoolName",
 * "away.mascot", "item.name" inside a loop). A small set of computed keys
 * that need real logic (e.g. joining the officials list into a
 * correctly-punctuated "X, Y, and Z" string) are registered explicitly below.
 */
class PlaceholderRegistry
{
    /** @return array<string, callable(array):string> */
    private static function computed(): array
    {
        return [
            'officials.joinedList' => function (array $context): string {
                $names = array_map(function (array $official): string {
                    return $official['name'];
                }, $context['officials'] ?? []);

                return self::joinWithAnd($names);
            },
            'home.coaches.joinedList' => function (array $context): string {
                return self::joinCoaches($context['home']['coaches'] ?? []);
            },
            'away.coaches.joinedList' => function (array $context): string {
                return self::joinCoaches($context['away']['coaches'] ?? []);
            },
            'item.battingOrderLabel' => function (array $context): string {
                $order = $context['item']['batting_order'] ?? null;

                return is_int($order) || ctype_digit((string) $order)
                    ? self::battingOrderLabel((int) $order)
                    : '';
            },
        ];
    }

    public static function resolve(string $key, array $context): string
    {
        $computed = self::computed();
        if (isset($computed[$key])) {
            return $computed[$key]($context);
        }

        $value = self::getPath($context, $key);

        if ($value === null) {
            return '{{missing:' . $key . '}}';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '{{invalid:' . $key . '}}';
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function getPath(array $context, string $path)
    {
        $segments = explode('.', $path);
        $current = $context;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param string[] $names
     */
    private static function joinWithAnd(array $names): string
    {
        $names = array_values(array_filter($names, function ($name): bool {
            return $name !== null && $name !== '';
        }));

        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0] . ' and ' . $names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names) . ', and ' . $last;
    }

    private static function battingOrderLabel(int $order): string
    {
        if ($order === 1) {
            return 'Leading off';
        }

        $suffix = 'th';
        if (!in_array($order % 100, [11, 12, 13], true)) {
            switch ($order % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
            }
        }

        return 'Batting ' . $order . $suffix;
    }

    /**
     * @param array<int, array{name:string, title?:string|null}> $coaches
     */
    private static function joinCoaches(array $coaches): string
    {
        $parts = array_map(function (array $coach): string {
            $title = $coach['title'] ?? null;

            return $title ? $coach['name'] . ', ' . $title : $coach['name'];
        }, $coaches);

        return self::joinWithAnd($parts);
    }
}
