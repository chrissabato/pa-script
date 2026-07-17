<?php

use Phinx\Seed\AbstractSeed;

/**
 * Willamette plus the master opponent-school lookup table pulled from the
 * "PA Scripts" Google Sheets folder (the Women's Soccer sheet's team/logo
 * lookup tab) — real schools/mascots/short-names the announcers already
 * use, not placeholder data. Idempotent: safe to re-run.
 *
 * A few things were deliberately dropped or adjusted from the source sheet:
 * - The sheet's duplicate "Willamette University [GOLD]" row (an alternate
 *   logo for the same school) and its "Home"/"Away" placeholder rows.
 * - "Bate College" — every other column was blank in the source, so there
 *   was nothing to import beyond a guess at the name.
 * - "Calvin College" — the same real institution as "Calvin University"
 *   (its current name; the college became a university in 2019), which
 *   already exists in this DB from earlier feature testing with attached
 *   games/programs. Importing the sheet's outdated name as a second row
 *   would just create a confusing duplicate.
 * - A few obvious source typos fixed: "Multnomah UNiversity" -> "...
 *   University", "Schneiner University"/"Mounatineers" -> "Schreiner
 *   University"/"Mountaineers" (confirmed by the sheet's own listed
 *   website domain, schreinermountaineers.com).
 * - Cal Lutheran and Claremont-Mudd-Scripps each field teams under two
 *   different mascots (men's/women's) — kept as the sheet has them, as two
 *   distinct rows, since this schema has one mascot per school row.
 * The sheet also lists an abbreviation and a logo filename per school;
 * neither has a column in this schema (not asked for), so both were left
 * out rather than added speculatively.
 */
class SchoolsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $wanted = [
            ['name' => 'Willamette University', 'mascot' => 'Bearcats', 'short_name' => 'Willamette'],
            ['name' => 'George Fox University', 'mascot' => 'Bruins', 'short_name' => 'George Fox'],
            ['name' => 'Pacific Lutheran University', 'mascot' => 'Lutes', 'short_name' => 'Pacific Lutheran'],
            ['name' => 'Pacific University', 'mascot' => 'Boxers', 'short_name' => 'Pacific'],
            ['name' => 'Linfield University', 'mascot' => 'Wildcats', 'short_name' => 'Linfield'],
            ['name' => 'Whitman College', 'mascot' => 'Blues', 'short_name' => 'Whitman'],
            ['name' => 'Whitworth University', 'mascot' => 'Pirates', 'short_name' => 'Whitworth'],
            ['name' => 'Lewis & Clark College', 'mascot' => 'River Otters', 'short_name' => 'Lewis & Clark'],
            ['name' => 'University of Puget Sound', 'mascot' => 'Loggers', 'short_name' => 'Puget Sound'],
            ['name' => 'Alfred State College', 'mascot' => 'Pioneers', 'short_name' => 'Alfred State'],
            ['name' => 'Alma College', 'mascot' => 'Scots', 'short_name' => 'Alma'],
            ['name' => 'Asbury University', 'mascot' => 'Eagles', 'short_name' => 'Asbury'],
            ['name' => 'Augsburg University', 'mascot' => 'Auggies', 'short_name' => 'Augsburg'],
            ['name' => 'Berry College', 'mascot' => 'Vikings', 'short_name' => 'Berry'],
            ['name' => 'Bethany Lutheran College', 'mascot' => 'Vikings', 'short_name' => 'Bethany Lutheran'],
            ['name' => 'Bushnell University', 'mascot' => 'Beacons', 'short_name' => 'Bushnell'],
            ['name' => 'Cal Lutheran University [Men]', 'mascot' => 'Kingsmen', 'short_name' => 'Cal Lutheran'],
            ['name' => 'Cal Lutheran University [Women]', 'mascot' => 'Regals', 'short_name' => 'Cal Lutheran'],
            ['name' => 'California Institute of Technology', 'mascot' => 'Beavers', 'short_name' => 'Caltech'],
            ['name' => 'Chapman University', 'mascot' => 'Panthers', 'short_name' => 'Chapman'],
            ['name' => 'Claremont-Mudd-Scripps [Men]', 'mascot' => 'Stags', 'short_name' => 'CMS'],
            ['name' => 'Claremont-Mudd-Scripps [Women]', 'mascot' => 'Athenas', 'short_name' => 'CMS'],
            ['name' => 'College of Idaho', 'mascot' => 'Yotes', 'short_name' => 'College of Idaho'],
            ['name' => 'Corban University', 'mascot' => 'Warriors', 'short_name' => 'Corban'],
            ['name' => 'Douglas College', 'mascot' => 'Royals', 'short_name' => 'Douglas'],
            ['name' => 'Eastern Oregon University', 'mascot' => 'Mountaineers', 'short_name' => 'Eastern Oregon'],
            ['name' => 'Emory University', 'mascot' => 'Eagles', 'short_name' => 'Emory'],
            ['name' => 'Evergreen State College', 'mascot' => 'Geoducks', 'short_name' => 'Evergreen State'],
            ['name' => 'Hamline University', 'mascot' => 'Pipers', 'short_name' => 'Hamline'],
            ['name' => 'Hardin-Simmons University', 'mascot' => 'Cowboys', 'short_name' => 'Hardin-Simmons'],
            ['name' => 'Hope College', 'mascot' => 'Flying Dutch', 'short_name' => 'Hope'],
            ['name' => 'Misericordia University', 'mascot' => 'Cougars', 'short_name' => 'Misericordia'],
            ['name' => 'Multnomah University', 'mascot' => 'Lions', 'short_name' => 'Multnomah'],
            ['name' => 'North Central College', 'mascot' => 'Cardinals', 'short_name' => 'North Central'],
            ['name' => 'Northwest Indian College', 'mascot' => 'Eagles', 'short_name' => 'Northwest Indian'],
            ['name' => 'Occidental College', 'mascot' => 'Tigers', 'short_name' => 'Occidental'],
            ['name' => 'Oglethorpe University', 'mascot' => 'Stormy Petrels', 'short_name' => 'Oglethorpe'],
            ['name' => 'Oregon Tech', 'mascot' => 'Owls', 'short_name' => 'Oregon Tech'],
            ['name' => 'Pacific Union College', 'mascot' => 'Pioneers', 'short_name' => 'Pacific Union'],
            ['name' => 'Pomona-Pitzer', 'mascot' => 'Sagehens', 'short_name' => 'Pomona-Pitzer'],
            ['name' => 'Portland Bible College', 'mascot' => 'Arrows', 'short_name' => 'Portland Bible'],
            ['name' => 'University of the South', 'mascot' => 'Tigers', 'short_name' => 'Sewanee'],
            ['name' => 'Simpson University', 'mascot' => 'Red Hawks', 'short_name' => 'Simpson'],
            ['name' => 'Southwestern Oregon CC', 'mascot' => 'Lakers', 'short_name' => 'Southwestern Oregon'],
            ['name' => 'SUNY Oneonta', 'mascot' => 'Red Dragons', 'short_name' => 'Oneonta'],
            ['name' => 'UC Santa Cruz', 'mascot' => 'Banana Slugs', 'short_name' => 'Santa Cruz'],
            ['name' => 'University of La Verne', 'mascot' => 'Leopards', 'short_name' => 'La Verne'],
            ['name' => 'University of Redlands', 'mascot' => 'Bulldogs', 'short_name' => 'Redlands'],
            ['name' => 'University of Wisconsin-River Falls', 'mascot' => 'Falcons', 'short_name' => 'River Falls'],
            ['name' => 'University of Wisconsin-Oshkosh', 'mascot' => 'Titans', 'short_name' => 'Oshkosh'],
            ['name' => 'Vancouver Island University', 'mascot' => 'Mariners', 'short_name' => 'Vancouver Island'],
            ['name' => 'Walla Walla University', 'mascot' => 'Wolves', 'short_name' => 'Walla Walla'],
            ['name' => 'Warner Pacific University', 'mascot' => 'Knights', 'short_name' => 'Warner Pacific'],
            ['name' => 'Washington and Lee University', 'mascot' => 'Generals', 'short_name' => 'Washington and Lee'],
            ['name' => 'West Coast Baptist College', 'mascot' => 'Eagles', 'short_name' => 'West Coast Baptist'],
            ['name' => 'Western Oregon University', 'mascot' => 'Wolves', 'short_name' => 'Western Oregon'],
            ['name' => 'Whittier College', 'mascot' => 'Poets', 'short_name' => 'Whittier'],
            ['name' => 'Trine University', 'mascot' => 'Thunder', 'short_name' => 'Trine'],
            ['name' => 'Anderson University', 'mascot' => 'Ravens', 'short_name' => 'Anderson'],
            ['name' => 'Bethany College', 'mascot' => 'Bisons', 'short_name' => 'Bethany'],
            ['name' => 'Ursuline College', 'mascot' => 'Arrows', 'short_name' => 'Ursuline'],
            ['name' => 'Schreiner University', 'mascot' => 'Mountaineers', 'short_name' => 'Schreiner'],
            ['name' => 'Centenary College', 'mascot' => 'Gents', 'short_name' => 'Centenary'],
            ['name' => 'LeTourneau University', 'mascot' => 'Yellowjackets', 'short_name' => 'LeTourneau'],
            ['name' => 'Austin College', 'mascot' => 'Kangaroos', 'short_name' => 'Austin'],
            ['name' => 'Milwaukee School of Engineering', 'mascot' => 'Raiders', 'short_name' => 'MSOE'],
            ['name' => 'Oberlin College', 'mascot' => 'Yeo', 'short_name' => 'Oberlin'],
        ];

        $data = array_values(array_filter($wanted, function (array $row): bool {
            $existing = $this->fetchRow(sprintf(
                "select id from schools where name = '%s'",
                str_replace("'", "''", $row['name'])
            ));

            return $existing === false;
        }));

        if ($data !== []) {
            $this->table('schools')->insert($data)->saveData();
        }
    }
}
