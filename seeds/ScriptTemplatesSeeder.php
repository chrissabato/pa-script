<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seeds script_templates rows per PLAN.md's two-axis design (division_id x
 * site_variant): three for Soccer (Women's/home, Men's/home, neutral) and
 * two for Basketball (Women's/home, Men's/home — no neutral variant was
 * read for Basketball). Idempotent: safe to re-run as later phases add more
 * sports' rows to this file.
 *
 * NOTE: the block content below is structurally representative of what was
 * read from the source Google Sheets during planning (welcome line, coach
 * line, starting lineup, officials, anthem cue, sportsmanship PSA) but is
 * NOT a verbatim transcription of the real sheet text — that text lives only
 * in the original sheets. Swap in the real wording via the template editor
 * (or a follow-up seed) before relying on this for an actual broadcast, and
 * diff the rendered output against a real sheet per PLAN.md's verification
 * approach.
 */
class ScriptTemplatesSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [
            SportsSeeder::class,
            DivisionsSeeder::class,
        ];
    }

    public function run(): void
    {
        $soccerId = $this->fetchRow("select id from sports where slug = 'soccer'")['id'];
        $basketballId = $this->fetchRow("select id from sports where slug = 'basketball'")['id'];
        $baseballId = $this->fetchRow("select id from sports where slug = 'baseball'")['id'];
        $softballId = $this->fetchRow("select id from sports where slug = 'softball'")['id'];
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $wanted = [
            [
                'sport_id' => $soccerId,
                'division_id' => $womensId,
                'site_variant' => 'home',
                'name' => "Soccer — Women's — Home",
                'content_blocks' => json_encode($this->homeBlocks('women')),
                'version' => 1,
            ],
            [
                'sport_id' => $soccerId,
                'division_id' => $mensId,
                'site_variant' => 'home',
                'name' => "Soccer — Men's — Home",
                'content_blocks' => json_encode($this->homeBlocks('men')),
                'version' => 1,
            ],
            [
                'sport_id' => $soccerId,
                'division_id' => null,
                'site_variant' => 'neutral',
                'name' => 'Soccer — Neutral Site',
                'content_blocks' => json_encode($this->neutralBlocks()),
                'version' => 1,
            ],
            [
                'sport_id' => $basketballId,
                'division_id' => $womensId,
                'site_variant' => 'home',
                'name' => "Basketball — Women's — Home",
                'content_blocks' => json_encode($this->basketballHomeBlocks('women')),
                'version' => 1,
            ],
            [
                'sport_id' => $basketballId,
                'division_id' => $mensId,
                'site_variant' => 'home',
                'name' => "Basketball — Men's — Home",
                'content_blocks' => json_encode($this->basketballHomeBlocks('men')),
                'version' => 1,
            ],
            [
                'sport_id' => $baseballId,
                'division_id' => null,
                'site_variant' => 'home',
                'name' => 'Baseball — Home',
                'content_blocks' => json_encode($this->battingOrderSportHomeBlocks('Baseball')),
                'version' => 1,
            ],
            [
                'sport_id' => $softballId,
                'division_id' => null,
                'site_variant' => 'home',
                'name' => 'Softball — Home',
                'content_blocks' => json_encode($this->battingOrderSportHomeBlocks('Softball')),
                'version' => 1,
            ],
        ];

        $data = array_values(array_filter($wanted, function (array $row): bool {
            $divisionClause = $row['division_id'] === null ? 'is null' : '= ' . (int) $row['division_id'];
            $existing = $this->fetchRow(sprintf(
                "select id from script_templates where sport_id = %d and division_id %s and site_variant = '%s'",
                $row['sport_id'],
                $divisionClause,
                $row['site_variant']
            ));

            return $existing === false;
        }));

        if ($data !== []) {
            $this->table('script_templates')->insert($data)->saveData();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeBlocks(string $gender): array
    {
        $teamAdjective = $gender === 'women' ? "Women's" : "Men's";

        return array_merge(
            [
                ['type' => 'text', 'content' => "Good afternoon, and welcome to "],
                ['type' => 'placeholder', 'key' => 'meta.venue'],
                ['type' => 'text', 'content' => " for today's Willamette {$teamAdjective} Soccer match, as your "],
                ['type' => 'placeholder', 'key' => 'home.mascot'],
                ['type' => 'text', 'content' => ' take on the '],
                ['type' => 'placeholder', 'key' => 'away.mascot'],
                ['type' => 'text', 'content' => ' from '],
                ['type' => 'placeholder', 'key' => 'away.schoolName'],
                ['type' => 'text', 'content' => ".\n\n"],
            ],
            $this->coachAndLineupBlocks(),
            $this->officialsAnthemPsaBlocks()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function neutralBlocks(): array
    {
        return array_merge(
            [
                ['type' => 'text', 'content' => "Good afternoon, and welcome to "],
                ['type' => 'placeholder', 'key' => 'meta.venue'],
                ['type' => 'text', 'content' => " for today's Soccer match between the "],
                ['type' => 'placeholder', 'key' => 'home.mascot'],
                ['type' => 'text', 'content' => ' of '],
                ['type' => 'placeholder', 'key' => 'home.schoolName'],
                ['type' => 'text', 'content' => ' and the '],
                ['type' => 'placeholder', 'key' => 'away.mascot'],
                ['type' => 'text', 'content' => ' of '],
                ['type' => 'placeholder', 'key' => 'away.schoolName'],
                ['type' => 'text', 'content' => ".\n\n"],
            ],
            $this->coachAndLineupBlocks(),
            $this->officialsAnthemPsaBlocks()
        );
    }

    /**
     * Basketball has no neutral-site variant (only Women's/Men's home sheets
     * were read) and, unlike Soccer, announces reserves.
     *
     * @return array<int, array<string, mixed>>
     */
    private function basketballHomeBlocks(string $gender): array
    {
        $teamAdjective = $gender === 'women' ? "Women's" : "Men's";

        return array_merge(
            [
                ['type' => 'text', 'content' => "Good afternoon, and welcome to "],
                ['type' => 'placeholder', 'key' => 'meta.venue'],
                ['type' => 'text', 'content' => " for today's Willamette {$teamAdjective} Basketball game, as your "],
                ['type' => 'placeholder', 'key' => 'home.mascot'],
                ['type' => 'text', 'content' => ' take on the '],
                ['type' => 'placeholder', 'key' => 'away.mascot'],
                ['type' => 'text', 'content' => ' from '],
                ['type' => 'placeholder', 'key' => 'away.schoolName'],
                ['type' => 'text', 'content' => ".\n\n"],
            ],
            $this->coachAndLineupBlocks(true),
            $this->officialsAnthemPsaBlocks()
        );
    }

    /**
     * Baseball/Softball: no divisions (single team per school) and no
     * neutral-site variant read. Lineup is ordered by batting order rather
     * than the starters/reserves pattern, with a standalone starting-pitcher
     * callout, and umpires get role-specific phrasing instead of a flat list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function battingOrderSportHomeBlocks(string $sportName): array
    {
        return array_merge(
            [
                ['type' => 'text', 'content' => "Good afternoon, and welcome to "],
                ['type' => 'placeholder', 'key' => 'meta.venue'],
                ['type' => 'text', 'content' => " for today's Willamette {$sportName} game, as your "],
                ['type' => 'placeholder', 'key' => 'home.mascot'],
                ['type' => 'text', 'content' => ' take on the '],
                ['type' => 'placeholder', 'key' => 'away.mascot'],
                ['type' => 'text', 'content' => ' from '],
                ['type' => 'placeholder', 'key' => 'away.schoolName'],
                ['type' => 'text', 'content' => ".\n\n"],
            ],
            $this->coachAndBattingOrderBlocks(),
            $this->umpiresAnthemPsaBlocks()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coachAndBattingOrderBlocks(): array
    {
        return array_merge([
            ['type' => 'text', 'content' => 'Head coach for '],
            ['type' => 'placeholder', 'key' => 'home.schoolName'],
            ['type' => 'text', 'content' => ' is '],
            ['type' => 'placeholder', 'key' => 'home.coaches.joinedList'],
            ['type' => 'text', 'content' => ".\n"],
            ['type' => 'text', 'content' => 'Head coach for '],
            ['type' => 'placeholder', 'key' => 'away.schoolName'],
            ['type' => 'text', 'content' => ' is '],
            ['type' => 'placeholder', 'key' => 'away.coaches.joinedList'],
            ['type' => 'text', 'content' => ".\n\n"],
        ],
            $this->battingOrderLineup('home'),
            [['type' => 'text', 'content' => "\n"]],
            $this->battingOrderLineup('away'),
            [['type' => 'text', 'content' => "\n"]]
        );
    }

    /**
     * Standalone starting-pitcher callout followed by the batting-order
     * loop for one side.
     *
     * @return array<int, array<string, mixed>>
     */
    private function battingOrderLineup(string $side): array
    {
        return [
            ['type' => 'text', 'content' => "Today's starting pitcher for "],
            ['type' => 'placeholder', 'key' => "{$side}.schoolName"],
            ['type' => 'text', 'content' => ' is '],
            ['type' => 'placeholder', 'key' => "{$side}.startingPitcher.name"],
            ['type' => 'text', 'content' => ".\n\n"],
            ['type' => 'text', 'content' => "Now, introducing the batting order for "],
            ['type' => 'placeholder', 'key' => "{$side}.schoolName"],
            ['type' => 'text', 'content' => ":\n"],
            [
                'type' => 'loop',
                'over' => "{$side}.battingOrder",
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
    }

    /**
     * Baseball/Softball variant of officialsAnthemPsaBlocks(): umpires get
     * role-specific phrasing ("behind the plate", "at first base", ...)
     * rather than a flat joined list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function umpiresAnthemPsaBlocks(): array
    {
        return array_merge(
            [
                ['type' => 'text', 'content' => "Today's umpires:\n"],
                [
                    'type' => 'loop',
                    'over' => 'officials',
                    'template' => [
                        ['type' => 'placeholder', 'key' => 'item.role'],
                        ['type' => 'text', 'content' => ', '],
                        ['type' => 'placeholder', 'key' => 'item.name'],
                        ['type' => 'text', 'content' => ".\n"],
                    ],
                ],
                ['type' => 'text', 'content' => "\n"],
            ],
            $this->anthemPsaBlocks()
        );
    }

    /**
     * Coach lines and starting-lineup loops, shared by home and neutral
     * variants — only the welcome framing above differs between them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function coachAndLineupBlocks(bool $includeReserves = false): array
    {
        return array_merge([
            ['type' => 'text', 'content' => 'Head coach for '],
            ['type' => 'placeholder', 'key' => 'home.schoolName'],
            ['type' => 'text', 'content' => ' is '],
            ['type' => 'placeholder', 'key' => 'home.coaches.joinedList'],
            ['type' => 'text', 'content' => ".\n"],
            ['type' => 'text', 'content' => 'Head coach for '],
            ['type' => 'placeholder', 'key' => 'away.schoolName'],
            ['type' => 'text', 'content' => ' is '],
            ['type' => 'placeholder', 'key' => 'away.coaches.joinedList'],
            ['type' => 'text', 'content' => ".\n\n"],

            ['type' => 'text', 'content' => "Now, introducing your starting lineup for "],
            ['type' => 'placeholder', 'key' => 'home.schoolName'],
            ['type' => 'text', 'content' => ":\n"],
        ],
            $this->starterLineupLoop('home', $includeReserves),
            [
            ['type' => 'text', 'content' => "\nAnd introducing the starting lineup for "],
            ['type' => 'placeholder', 'key' => 'away.schoolName'],
            ['type' => 'text', 'content' => ":\n"],
        ],
            $this->starterLineupLoop('away', $includeReserves),
            [
            ['type' => 'text', 'content' => "\n"],
        ]
        );
    }

    /**
     * Starting-lineup loop for one side, with an optional trailing reserves
     * loop (Basketball/Volleyball/Lacrosse announce reserves with a plain
     * "Number {#}, {name}" line; Soccer's read sheets did not).
     *
     * @return array<int, array<string, mixed>>
     */
    private function starterLineupLoop(string $side, bool $includeReserves): array
    {
        $blocks = [
            [
                'type' => 'loop',
                'over' => "{$side}.starters",
                'template' => [
                    ['type' => 'text', 'content' => 'At '],
                    ['type' => 'placeholder', 'key' => 'item.position'],
                    ['type' => 'text', 'content' => ', a '],
                    ['type' => 'placeholder', 'key' => 'item.year'],
                    ['type' => 'text', 'content' => ' from '],
                    ['type' => 'placeholder', 'key' => 'item.hometown'],
                    ['type' => 'text', 'content' => ', number '],
                    ['type' => 'placeholder', 'key' => 'item.number'],
                    ['type' => 'text', 'content' => ', '],
                    ['type' => 'placeholder', 'key' => 'item.name'],
                    ['type' => 'text', 'content' => ".\n"],
                ],
            ],
        ];

        if ($includeReserves) {
            $blocks[] = [
                'type' => 'loop',
                'over' => "{$side}.reserves",
                'template' => [
                    ['type' => 'text', 'content' => 'Number '],
                    ['type' => 'placeholder', 'key' => 'item.number'],
                    ['type' => 'text', 'content' => ', '],
                    ['type' => 'placeholder', 'key' => 'item.name'],
                    ['type' => 'text', 'content' => ".\n"],
                ],
            ];
        }

        return $blocks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function officialsAnthemPsaBlocks(): array
    {
        return array_merge(
            [
                ['type' => 'text', 'content' => "Today's officials are "],
                ['type' => 'placeholder', 'key' => 'officials.joinedList'],
                ['type' => 'text', 'content' => ".\n\n"],
            ],
            $this->anthemPsaBlocks()
        );
    }

    /**
     * Anthem cue and sportsmanship PSA — generic PA-program boilerplate
     * shared by every sport regardless of how officials/lineup are announced.
     *
     * @return array<int, array<string, mixed>>
     */
    private function anthemPsaBlocks(): array
    {
        return [
            ['type' => 'text', 'content' => 'Please rise and remove your caps for the singing of our national anthem'],
            ['type' => 'placeholder', 'key' => 'meta.anthemNote'],
            ['type' => 'text', 'content' => ".\n\n"],
            [
                'type' => 'text',
                'content' => "In the spirit of good sportsmanship, we ask that all fans support the student-athletes "
                    . "of both teams with positive cheers, and remember that officials, coaches, and players are "
                    . "here to compete with integrity and mutual respect.\n",
            ],
        ];
    }
}
