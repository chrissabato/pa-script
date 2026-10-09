<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Data the real PA scripts read that the app didn't store yet (from the
 * source sheets' Setup tab):
 * - games: conference game ("Northwest Conference" vs "non-conference")
 *   and an optional special announcement read before the visitor intro.
 * - players: captain flag ("A captain, at goalie, ...").
 * - coaches: possessive pronoun and season count for the head-coach line
 *   ("... is Jim Tursi in his 17th season").
 */
final class AddScriptFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('is_conference', 'boolean', ['default' => false])
            ->addColumn('special_announcement', 'text', ['null' => true])
            ->update();

        $this->table('game_roster_players')
            ->addColumn('is_captain', 'boolean', ['default' => false])
            ->update();

        $this->table('game_roster_coaches')
            ->addColumn('pronoun', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('seasons', 'integer', ['null' => true])
            ->update();
    }
}
