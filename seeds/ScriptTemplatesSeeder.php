<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seeds script_templates rows per PLAN.md's two-axis design (division_id x
 * site_variant): three for Soccer (Women's/home, Men's/home, neutral), two
 * each for Basketball and Lacrosse (Women's/home, Men's/home), and one
 * each for Baseball, Softball and Volleyball (divisionless, home-only per
 * PLAN.md's Phase 3 notes). Safe to re-run: this file is the source of
 * truth for template content, so existing rows are updated in place.
 *
 * Wording status: Women's and Men's Soccer (home), Basketball,
 * Volleyball and Lacrosse are transcribed from their real sheets'
 * Script-tab formulas (verified by tests/Rendering/WomensSoccerScriptTest.php,
 * BasketballScriptTest.php, VolleyballScriptTest.php and
 * LacrosseScriptTest.php; the two soccer sheets differ only in
 * "Women's"/"Men's"). Every other
 * template is still placeholder wording with the right structure — port
 * each from its sheet the same way before relying on it for a broadcast.
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
        $volleyballId = $this->fetchRow("select id from sports where slug = 'volleyball'")['id'];
        $lacrosseId = $this->fetchRow("select id from sports where slug = 'lacrosse'")['id'];
        $womensId = $this->fetchRow("select id from divisions where slug = 'womens'")['id'];
        $mensId = $this->fetchRow("select id from divisions where slug = 'mens'")['id'];

        $wanted = [
            [
                'sport_id' => $soccerId,
                'division_id' => $womensId,
                'site_variant' => 'home',
                'name' => "Soccer — Women's — Home",
                'content_blocks' => json_encode($this->soccerHomeBlocks("Women's")),
                'version' => 1,
            ],
            [
                'sport_id' => $soccerId,
                'division_id' => $mensId,
                'site_variant' => 'home',
                'name' => "Soccer — Men's — Home",
                'content_blocks' => json_encode($this->soccerHomeBlocks("Men's")),
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
                'content_blocks' => json_encode($this->basketballHomeBlocks("Women's")),
                'version' => 1,
            ],
            [
                'sport_id' => $basketballId,
                'division_id' => $mensId,
                'site_variant' => 'home',
                'name' => "Basketball — Men's — Home",
                'content_blocks' => json_encode($this->basketballHomeBlocks("Men's")),
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
            [
                'sport_id' => $volleyballId,
                'division_id' => null,
                'site_variant' => 'home',
                'name' => 'Volleyball — Home',
                'content_blocks' => json_encode($this->volleyballBlocks()),
                'version' => 1,
            ],
            [
                'sport_id' => $lacrosseId,
                'division_id' => $womensId,
                'site_variant' => 'home',
                'name' => "Lacrosse — Women's — Home",
                'content_blocks' => json_encode($this->lacrosseHomeBlocks("Women's")),
                'version' => 1,
            ],
            [
                'sport_id' => $lacrosseId,
                'division_id' => $mensId,
                'site_variant' => 'home',
                'name' => "Lacrosse — Men's — Home",
                'content_blocks' => json_encode($this->lacrosseHomeBlocks("Men's")),
                'version' => 1,
            ],
        ];

        // Rows a template split replaced (Lacrosse was one divisionless
        // template until it got the Soccer-style Women's/Men's pair).
        $this->execute(sprintf(
            "delete from script_templates where sport_id = %d and division_id is null and site_variant = 'home'",
            $lacrosseId
        ));

        $data = [];
        foreach ($wanted as $row) {
            $divisionClause = $row['division_id'] === null ? 'is null' : '= ' . (int) $row['division_id'];
            $existing = $this->fetchRow(sprintf(
                "select id from script_templates where sport_id = %d and division_id %s and site_variant = '%s'",
                $row['sport_id'],
                $divisionClause,
                $row['site_variant']
            ));

            if ($existing === false) {
                $data[] = $row;
                continue;
            }
            $this->getAdapter()->getConnection()
                ->prepare(
                    'update script_templates set name = :name, content_blocks = :content_blocks, '
                    . 'updated_at = current_timestamp where id = :id'
                )
                ->execute(['name' => $row['name'], 'content_blocks' => $row['content_blocks'], 'id' => $existing['id']]);
        }

        if ($data !== []) {
            $this->table('script_templates')->insert($data)->saveData();
        }
    }

    // ---- Block helpers (keep transcribed templates readable) -------------

    /** @return array<string, mixed> */
    private static function t(string $text): array
    {
        return ['type' => 'text', 'content' => $text];
    }

    /** @return array<string, mixed> */
    private static function p(string $key): array
    {
        return ['type' => 'placeholder', 'key' => $key];
    }

    /**
     * @param array<int, array<string, mixed>> $then
     * @param array<int, array<string, mixed>> $else
     * @return array<string, mixed>
     */
    private static function when(string $key, array $then, array $else = []): array
    {
        return ['type' => 'when', 'key' => $key, 'then' => $then, 'else' => $else];
    }

    /**
     * The game's venue, or the sport's usual home facility when none is set.
     *
     * @return array<string, mixed>
     */
    private static function venueOr(string $defaultVenue): array
    {
        return self::when('meta.venue', [self::p('meta.venue')], [self::t($defaultVenue)]);
    }

    /** @return array<string, mixed> */
    private static function todayOrTonight(): array
    {
        return self::when('meta.isEvening', [self::t("tonight's")], [self::t("today's")]);
    }

    /**
     * The game's closing announcement, read after everything else.
     *
     * @return array<string, mixed>
     */
    private static function closingAnnouncement(): array
    {
        return self::when('meta.closingAnnouncement', [self::t("\n"), self::p('meta.closingAnnouncement'), self::t("\n")]);
    }

    // ---- Soccer (real sheet wording) ---------------------------------------

    /**
     * Transcribed from the "PA Script - Women's Soccer" sheet's Script tab:
     * welcome, visitors' starters + head coach, home starters + head coach,
     * officials, anthem, sportsmanship statement. Wording fixes vs. the
     * sheet: its typos ("Prfanity", "enviroment", missing "a") and a
     * stray double space are corrected, coach lines end with a period, and
     * the officials use a proper "A, B, and C" list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function soccerHomeBlocks(string $divisionLabel): array
    {
        return $this->fieldSportHomeBlocks(
            "{$divisionLabel} Soccer",
            "Willamette University's ",
            'And now the playing of the National Anthem.'
        );
    }

    /**
     * Transcribed from the "PA Script - Lacrosse" sheet's Script tab, the
     * same formulas as Soccer's apart from the sport, no "Willamette
     * University's" before the venue, and "And now for the playing of the
     * National Anthem." Same wording fixes as Soccer. The sheet is the
     * Women's game; Men's reads the same with "Men's".
     *
     * @return array<int, array<string, mixed>>
     */
    private function lacrosseHomeBlocks(string $divisionLabel): array
    {
        return $this->fieldSportHomeBlocks(
            "{$divisionLabel} Lacrosse",
            '',
            'And now for the playing of the National Anthem.'
        );
    }

    /**
     * The Soccer/Lacrosse sheets' shared script: welcome, visitors'
     * starters + head coach, home starters + head coach, officials,
     * anthem, sportsmanship statement.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fieldSportHomeBlocks(string $gameLabel, string $venuePrefix, string $anthemLine): array
    {
        $venue = self::venueOr('Sparks Field');

        return array_merge(
            [
                self::t('Good '), self::p('meta.partOfDay'),
                self::t(" and welcome to {$venuePrefix}"), $venue,
                self::t(' on the land of the Kalapuya for '), self::todayOrTonight(), self::t(' '),
                self::when('meta.isConference', [self::t('Northwest Conference')], [self::t('non-conference')]),
                self::t(" {$gameLabel} game between the "),
                self::p('away.schoolName'), self::t(' '), self::p('away.mascot'),
                self::t(' and the '),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'),
                self::t(". Before the game gets underway, let's meet the starters.\n\n"),
                self::t('First for the visitors from '), self::p('away.shortName'), self::t("\n"),
            ],
            $this->starterLines('away'),
            $this->headCoachLine('away'),
            [
                self::t("\nAnd now, the starting lineup for the "),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'), self::t("\n"),
            ],
            $this->starterLines('home'),
            $this->headCoachLine('home'),
            [
                self::when('officials', [
                    self::t("\nThe officials for "), self::todayOrTonight(),
                    self::t(' game are '), self::p('officials.joinedList'), self::t(".\n"),
                ]),
                self::when('meta.specialAnnouncement', [self::t("\n"), self::p('meta.specialAnnouncement'), self::t("\n")]),
                self::when('meta.hasAnthem', [self::t("\n{$anthemLine}\n")]),
                self::t("\nWillamette University, the Northwest Conference, and the NCAA promote good sportsmanship "
                    . 'by the student-athletes, coaches, and fans. We request your cooperation in supporting the '
                    . 'student-athletes and officials in a positive manner. Profanity, derogatory comments or other '
                    . 'intimidating actions directed at athletes, officials, team representatives or other fans will '
                    . 'not be tolerated and are grounds for removal from '),
                $venue,
                self::t(".\n\nWillamette appreciates your cooperation in creating a safe and positive game environment.\n"),
                self::closingAnnouncement(),
            ]
        );
    }

    // ---- Volleyball (real sheet wording) -----------------------------------

    /**
     * Transcribed from the "PA Script - Volleyball" sheet (Setup!J1:J6 and
     * the Filters tab): welcome + anthem, then each team's reserves
     * ("Number 4, name") before its starters, visitors first, with the
     * walk-out music cue before Willamette's introduction. Officials are
     * read by role ("referees A and B, and linesmen C and D"). Fixes vs. the
     * sheet: typos ("Prfanity", "Conr Fieldhouse", "lets", "enviroment")
     * and a missing comma before "number".
     *
     * @return array<int, array<string, mixed>>
     */
    private function volleyballBlocks(): array
    {
        $venue = self::venueOr('Cone Field House');
        $reserves = function (string $side): array {
            return [[
                'type' => 'loop',
                'over' => $side . '.reserves',
                'template' => [
                    self::when('item.number', [self::t('Number '), self::p('item.number'), self::t(', ')]),
                    self::p('item.spokenName'), self::t("\n"),
                ],
            ]];
        };

        return array_merge(
            [
                self::t('Good '), self::p('meta.partOfDay'),
                self::t(" and welcome to Willamette University's "), $venue,
                self::t(' on the land of the Kalapuya for '), self::todayOrTonight(), self::t(' '),
                self::when('meta.isConference', [self::t('Northwest Conference')], [self::t('non-conference')]),
                self::t(' Volleyball match between the '),
                self::p('away.schoolName'), self::t(' '), self::p('away.mascot'),
                self::t(' and your '),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'),
                self::t(".\n\n"),
                self::when('meta.specialAnnouncement', [self::p('meta.specialAnnouncement'), self::t("\n\n")]),
                self::when('meta.hasAnthem', [self::t("And now for the playing of the National Anthem.\n\n")]),
                self::t("Before the match gets underway, let's meet the teams.\n\n"),
                self::when('away.reserves', [
                    self::t('First, the reserves for the visitors from '), self::p('away.shortName'), self::t("\n"),
                ]),
            ],
            $reserves('away'),
            [self::t("\nAnd now the starters for the "), self::p('away.mascot'), self::t("\n")],
            $this->starterLines('away'),
            $this->headCoachLine('away'),
            [
                self::t("\n[start Joker and the Thief]\nNow let's meet your "),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'),
                self::when('home.reserves', [self::t(". First the reserves\n")], [self::t(".\n")]),
            ],
            $reserves('home'),
            [self::t("\nNow the starters for the "), self::p('home.mascot'), self::t(".\n")],
            $this->starterLines('home'),
            $this->headCoachLine('home'),
            [
                self::when('officials', [
                    self::t("\nThe officials for "), self::todayOrTonight(),
                    self::t(' game are '), self::p('officials.spokenList'), self::t(".\n"),
                ]),
                self::t("\nWillamette University, the Northwest Conference, and the NCAA promote good sportsmanship "
                    . 'by the student-athletes, coaches, and fans. We request your cooperation in supporting the '
                    . 'student-athletes and officials in a positive manner. Profanity, derogatory comments or other '
                    . 'intimidating actions directed at athletes, officials, team representatives or other fans will '
                    . 'not be tolerated and are grounds for removal from '),
                $venue,
                self::t(".\n\nWillamette appreciates your cooperation in creating a safe and positive game environment.\n"),
                self::closingAnnouncement(),
            ]
        );
    }

    // ---- Basketball (real sheet wording) -----------------------------------

    /**
     * Transcribed from the "PA Script - Women's Basketball" and "PA Script -
     * Men's Basketball" sheets' Script tabs: welcome + anthem, visitors'
     * starters + head coach, home starters + head coach, officials,
     * sportsmanship statement. The sheets differ beyond "Women's"/"Men's" —
     * only Men's has the walk-out music cue before Willamette's lineup, only
     * Women's the closing "Willamette appreciates" line — but both divisions
     * get both. The Women's sheet's Bearcats app plug is left out. Fixes vs.
     * the sheets: typos ("enviroment"), a missing "a",
     * a stray double space, and the officials use a proper "A, B, and C" list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function basketballHomeBlocks(string $divisionLabel): array
    {
        $venue = self::venueOr('Cone Field House');

        return array_merge(
            [
                self::t('Good '), self::p('meta.partOfDay'),
                self::t(" and welcome to Willamette University's "), $venue,
                self::t(' on the land of the Kalapuya for '), self::todayOrTonight(), self::t(' '),
                self::when('meta.isConference', [self::t('Northwest Conference')], [self::t('non-conference')]),
                self::t(" {$divisionLabel} Basketball game between the "),
                self::p('away.schoolName'), self::t(' '), self::p('away.mascot'),
                self::t(' and the '),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'),
                self::t('.'),
                self::when('meta.specialAnnouncement', [self::t("\n\n"), self::p('meta.specialAnnouncement')]),
                self::when('meta.hasAnthem', [
                    self::when('meta.specialAnnouncement', [self::t("\n\n")], [self::t(' ')]),
                    self::t('And now for the playing of the National Anthem.'),
                ]),
                self::t("\n\n"),
                self::t("Before the game gets underway, let's meet the starting lineups.\n\n"),
                self::t('First for the visitors from '), self::p('away.shortName'), self::t("\n"),
            ],
            $this->starterLines('away'),
            $this->headCoachLine('away'),
            [
                self::t("\n[START Joker and the Thief]\n"),
                self::t("\nAnd now, the starting lineup for the "),
                self::p('home.schoolName'), self::t(' '), self::p('home.mascot'), self::t("\n"),
            ],
            $this->starterLines('home'),
            $this->headCoachLine('home'),
            [
                self::when('officials', [
                    self::t("\nThe officials for "), self::todayOrTonight(),
                    self::t(' game are '), self::p('officials.joinedList'), self::t(".\n"),
                ]),
                self::t("\nWillamette University, the Northwest Conference, and the NCAA promote good sportsmanship "
                    . 'by the student-athletes, coaches, and fans. We request your cooperation in supporting the '
                    . 'student-athletes and officials in a positive manner. Profanity, derogatory comments or other '
                    . 'intimidating actions directed at athletes, officials, team representatives or other fans will '
                    . 'not be tolerated and are grounds for removal from '),
                $venue,
                self::t(".\n"),
                self::t("\nWillamette appreciates your cooperation in creating a safe and positive game environment.\n"),
                self::closingAnnouncement(),
            ]
        );
    }

    // ---- Shared lineup lines ---------------------------------------------

    /**
     * "A captain, at goalie, a senior from Portland, Oregon, number 1,
     * SEE-era ED-werds" — one line per starter.
     *
     * @return array<int, array<string, mixed>>
     */
    private function starterLines(string $side): array
    {
        return [[
            'type' => 'loop',
            'over' => $side . '.starters',
            'template' => [
                self::when('item.is_captain', [self::t('A captain, at ')], [self::t('At ')]),
                self::p('item.spokenPosition'),
                self::when('item.spokenYear', [self::t(', a '), self::p('item.spokenYear')]),
                self::when('item.spokenHometown', [self::t(' from '), self::p('item.spokenHometown')]),
                self::when('item.number', [self::t(', number '), self::p('item.number')]),
                self::t(', '), self::p('item.spokenName'), self::t("\n"),
            ],
        ]];
    }

    /**
     * "Lewis & Clark's head coach is Jim Tursi in his 17th season and is
     * assisted by Fraser Morrison, Rebecca McCullough, and Bill Irwin."
     *
     * @return array<int, array<string, mixed>>
     */
    private function headCoachLine(string $side): array
    {
        return [self::when($side . '.headCoach', [
            self::p($side . '.shortName'), self::t("'s head coach is "), self::p($side . '.headCoach.spokenName'),
            self::when($side . '.headCoach.seasonsOrdinal', [
                self::t(' in '),
                self::when($side . '.headCoach.pronoun', [self::p($side . '.headCoach.pronoun')], [self::t('their')]),
                self::t(' '), self::p($side . '.headCoach.seasonsOrdinal'), self::t(' season'),
            ]),
            self::when($side . '.assistantCoaches', [
                self::t(' and is assisted by '), self::p($side . '.assistantCoaches'),
            ]),
            self::t(".\n"),
        ])];
    }

    // ---- Placeholder templates (not yet ported from their sheets) ----------

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
     * $beforeHomeLineupBlocks is an insertion point for sport-specific inline
     * beats between the coach lines and the home lineup call (e.g.
     * Volleyball's walk-out music cue).
     *
     * @param array<int, array<string, mixed>> $beforeHomeLineupBlocks
     * @return array<int, array<string, mixed>>
     */
    private function coachAndLineupBlocks(bool $includeReserves = false, array $beforeHomeLineupBlocks = []): array
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
            $beforeHomeLineupBlocks,
            [
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
            self::when('meta.specialAnnouncement', [self::p('meta.specialAnnouncement'), self::t("\n\n")]),
            self::when('meta.hasAnthem', [
                self::t('Please rise and remove your caps for the singing of our national anthem'),
                self::p('meta.anthemNote'),
                self::t(".\n\n"),
            ]),
            [
                'type' => 'text',
                'content' => "In the spirit of good sportsmanship, we ask that all fans support the student-athletes "
                    . "of both teams with positive cheers, and remember that officials, coaches, and players are "
                    . "here to compete with integrity and mutual respect.\n",
            ],
            self::closingAnnouncement(),
        ];
    }
}
