<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * The friendly names the template editor shows for render-context keys:
 * `{away school}` for away.schoolName, `[if conference game]` for
 * meta.isConference, `[each home starters]` for home.starters. A name can
 * stand for up to three keys, by where it's used — `value` inside {…},
 * `test` after [if …], `list` after [each …] — since e.g. "away head
 * coach" reads the coach's spoken name but tests whether there's a coach
 * at all. Inside an [each], the list's `items` kind adds its item names
 * ({name}, {position}, ...).
 *
 * Keys with no friendly name still work written raw ({meta.date}).
 */
class FieldCatalog
{
    /**
     * @return array<int, array{label: string, group: string, hint: string, value?: string, test?: string, list?: string, items?: string}>
     */
    public static function fields(): array
    {
        $fields = [
            ['label' => 'part of day', 'group' => 'Game', 'hint' => 'morning / afternoon / evening', 'value' => 'meta.partOfDay'],
            ['label' => 'evening', 'group' => 'Game', 'hint' => 'Game starts at 5 pm or later', 'test' => 'meta.isEvening'],
            ['label' => 'conference game', 'group' => 'Game', 'hint' => 'Conference box ticked', 'test' => 'meta.isConference'],
            ['label' => 'venue', 'group' => 'Game', 'hint' => "The game's venue, else the sport's default (home games)", 'value' => 'meta.venue', 'test' => 'meta.venue'],
            ['label' => 'game venue', 'group' => 'Game', 'hint' => 'Only a venue typed on the game', 'value' => 'meta.gameVenue', 'test' => 'meta.gameVenue'],
            ['label' => 'team', 'group' => 'Game', 'hint' => "e.g. Women's Soccer, Softball", 'value' => 'meta.teamName'],
            ['label' => 'sport', 'group' => 'Game', 'hint' => 'Compare: [if sport = volleyball]', 'test' => 'meta.sport'],
            ['label' => 'anthem', 'group' => 'Game', 'hint' => 'The anthem is played', 'test' => 'meta.hasAnthem'],
            ['label' => 'special announcement', 'group' => 'Game', 'hint' => 'Read before the anthem', 'value' => 'meta.specialAnnouncement', 'test' => 'meta.specialAnnouncement'],
            ['label' => 'closing announcement', 'group' => 'Game', 'hint' => 'Read at the end', 'value' => 'meta.closingAnnouncement', 'test' => 'meta.closingAnnouncement'],
        ];

        foreach (['away' => 'Visitors', 'home' => 'Home team'] as $side => $group) {
            $fields = array_merge($fields, [
                ['label' => "{$side} school", 'group' => $group, 'hint' => 'Full school name', 'value' => "{$side}.schoolName"],
                ['label' => "{$side} short name", 'group' => $group, 'hint' => 'e.g. Lewis & Clark', 'value' => "{$side}.shortName"],
                ['label' => "{$side} mascot", 'group' => $group, 'hint' => 'e.g. Pioneers', 'value' => "{$side}.mascot"],
                ['label' => "{$side} starters", 'group' => $group, 'hint' => 'Starters, by number', 'list' => "{$side}.starters", 'test' => "{$side}.starters", 'items' => 'player'],
                ['label' => "{$side} reserves", 'group' => $group, 'hint' => 'Everyone else, by number', 'list' => "{$side}.reserves", 'test' => "{$side}.reserves", 'items' => 'player'],
                ['label' => "{$side} batting order", 'group' => $group, 'hint' => 'Baseball/Softball: by Bat slot', 'list' => "{$side}.battingOrder", 'test' => "{$side}.battingOrder", 'items' => 'player'],
                ['label' => "{$side} head coach", 'group' => $group, 'hint' => 'Spoken name', 'value' => "{$side}.headCoach.spokenName", 'test' => "{$side}.headCoach"],
                ['label' => "{$side} head coach seasons", 'group' => $group, 'hint' => 'e.g. 17th', 'value' => "{$side}.headCoach.seasonsOrdinal", 'test' => "{$side}.headCoach.seasonsOrdinal"],
                ['label' => "{$side} head coach pronoun", 'group' => $group, 'hint' => 'his / her / their', 'value' => "{$side}.headCoach.pronoun", 'test' => "{$side}.headCoach.pronoun"],
                ['label' => "{$side} assistant coaches", 'group' => $group, 'hint' => '"A, B, and C"', 'value' => "{$side}.assistantCoaches", 'test' => "{$side}.assistantCoaches"],
                ['label' => "{$side} coaches", 'group' => $group, 'hint' => 'Whole staff with titles', 'value' => "{$side}.coaches.joinedList"],
            ]);
        }

        return array_merge($fields, [
            ['label' => 'officials', 'group' => 'Officials', 'hint' => '"A, B, and C"', 'value' => 'officials.joinedList', 'test' => 'officials', 'list' => 'officials', 'items' => 'official'],
            ['label' => 'officials by role', 'group' => 'Officials', 'hint' => '"referees A and B, and linesmen C and D"', 'value' => 'officials.spokenList'],
            ['label' => 'umpires', 'group' => 'Officials', 'hint' => '"A behind the plate, and B covering the bases"', 'value' => 'officials.umpireList'],
        ]);
    }

    /**
     * Names usable inside an [each] over a list of the given kind.
     *
     * @return array<int, array{label: string, hint: string, value?: string, test?: string}>
     */
    public static function itemFields(string $kind): array
    {
        if ($kind === 'official') {
            return [
                ['label' => 'name', 'hint' => 'Official\'s name', 'value' => 'item.name', 'test' => 'item.name'],
                ['label' => 'role', 'hint' => 'As typed on the officials list', 'value' => 'item.role', 'test' => 'item.role'],
            ];
        }

        return [
            ['label' => 'name', 'hint' => 'Name as set on the Script tab', 'value' => 'item.spokenName'],
            ['label' => 'number', 'hint' => 'Jersey number', 'value' => 'item.number', 'test' => 'item.number'],
            ['label' => 'position', 'hint' => 'Spoken, e.g. goalie', 'value' => 'item.spokenPosition', 'test' => 'item.spokenPosition'],
            ['label' => 'playing position', 'hint' => 'Baseball/Softball: where they play today', 'value' => 'item.spokenLineupPosition', 'test' => 'item.spokenLineupPosition'],
            ['label' => 'year', 'hint' => 'e.g. junior', 'value' => 'item.spokenYear', 'test' => 'item.spokenYear'],
            ['label' => 'hometown', 'hint' => 'e.g. Portland, Oregon', 'value' => 'item.spokenHometown', 'test' => 'item.spokenHometown'],
            ['label' => 'captain', 'hint' => 'Captain box ticked', 'test' => 'item.is_captain'],
            ['label' => 'batting slot', 'hint' => '"Leading off", "Batting 2nd" — test against 1–10', 'value' => 'item.battingOrderLabel', 'test' => 'item.batting_order'],
        ];
    }

    /**
     * The key a name stands for in a role (value/test/list), with the
     * enclosing [each]'s item kind, or null if the name isn't known there.
     */
    public static function keyFor(string $label, string $role, ?string $itemKind): ?string
    {
        $label = self::normalize($label);
        foreach (self::candidates($itemKind) as $field) {
            if (self::normalize($field['label']) === $label) {
                return $field[$role] ?? null;
            }
        }

        return null;
    }

    /** The friendly name for a key in a role, or null to write it raw. */
    public static function labelFor(string $key, string $role, ?string $itemKind): ?string
    {
        foreach (self::candidates($itemKind) as $field) {
            if (($field[$role] ?? null) === $key) {
                return $field['label'];
            }
        }

        return null;
    }

    /** The item kind ("player"/"official") of a list key, if known. */
    public static function itemKindOf(string $listKey): ?string
    {
        foreach (self::fields() as $field) {
            if (($field['list'] ?? null) === $listKey) {
                return $field['items'];
            }
        }

        return null;
    }

    /** @return array<int, array<string, string>> item names first, so they win inside an [each] */
    private static function candidates(?string $itemKind): array
    {
        return array_merge($itemKind !== null ? self::itemFields($itemKind) : [], self::fields());
    }

    private static function normalize(string $label): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $label)));
    }
}
