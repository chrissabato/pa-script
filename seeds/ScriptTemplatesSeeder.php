<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seeds the three Soccer script_templates rows (Women's/home, Men's/home,
 * neutral) per PLAN.md's two-axis design (division_id x site_variant).
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
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $data = [
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
        ];

        $this->table('script_templates')->insert($data)->saveData();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeBlocks(string $gender): array
    {
        $teamAdjective = $gender === 'women' ? "Women's" : "Men's";

        return array_merge(
            [
                ['type' => 'text', 'content' => "Good afternoon, and welcome to Bearcat Field for today's Willamette {$teamAdjective} Soccer match, as your "],
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
                ['type' => 'text', 'content' => "Good afternoon, and welcome to Bearcat Field for today's Soccer match between the "],
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
     * Coach lines and starting-lineup loops, shared by home and neutral
     * variants — only the welcome framing above differs between them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function coachAndLineupBlocks(): array
    {
        return [
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
            [
                'type' => 'loop',
                'over' => 'home.starters',
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
            ['type' => 'text', 'content' => "\nAnd introducing the starting lineup for "],
            ['type' => 'placeholder', 'key' => 'away.schoolName'],
            ['type' => 'text', 'content' => ":\n"],
            [
                'type' => 'loop',
                'over' => 'away.starters',
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
            ['type' => 'text', 'content' => "\n"],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function officialsAnthemPsaBlocks(): array
    {
        return [
            ['type' => 'text', 'content' => "Today's officials are "],
            ['type' => 'placeholder', 'key' => 'officials.joinedList'],
            ['type' => 'text', 'content' => ".\n\n"],
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
