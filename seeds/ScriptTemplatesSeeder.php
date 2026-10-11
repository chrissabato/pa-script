<?php

use App\Templates\DefaultTemplates;
use Phinx\Seed\AbstractSeed;

/**
 * Seeds script_templates rows per PLAN.md's two-axis design (division_id x
 * site_variant) and the default global snippets (script_snippets) from
 * App\Templates\DefaultTemplates, which holds the wording. Safe to re-run:
 * missing rows are created and existing ones reset to the default wording —
 * except those edited in the app (edited_at set), which keep their edits
 * until someone resets them there. Snippets added in the app are left alone.
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
        $sportIds = [];
        foreach ($this->fetchAll('select id, slug from sports') as $row) {
            $sportIds[$row['slug']] = (int) $row['id'];
        }
        $divisionIds = [];
        foreach ($this->fetchAll('select id, slug from divisions') as $row) {
            $divisionIds[$row['slug']] = (int) $row['id'];
        }

        // Rows a template split replaced (Lacrosse was one divisionless
        // template until it got the Soccer-style Women's/Men's pair).
        $this->execute(sprintf(
            "delete from script_templates where sport_id = %d and division_id is null and site_variant = 'home'",
            $sportIds['lacrosse']
        ));

        $connection = $this->getAdapter()->getConnection();

        // Default snippets first: the templates use them.
        foreach ((new DefaultTemplates())->snippets() as $name => $blocks) {
            $existing = $this->fetchRow(sprintf(
                'select id, edited_at from script_snippets where name = %s',
                $connection->quote($name)
            ));
            if ($existing === false) {
                $connection->prepare('insert into script_snippets (name, content_blocks) values (:name, :content_blocks)')
                    ->execute(['name' => $name, 'content_blocks' => json_encode($blocks)]);
            } elseif ($existing['edited_at'] === null) {
                $connection->prepare(
                    'update script_snippets set content_blocks = :content_blocks, updated_at = current_timestamp where id = :id'
                )->execute(['content_blocks' => json_encode($blocks), 'id' => $existing['id']]);
            }
        }

        $data = [];
        foreach ((new DefaultTemplates())->all() as $template) {
            $row = [
                'sport_id' => $sportIds[$template['sport']],
                'division_id' => $template['division'] !== null ? $divisionIds[$template['division']] : null,
                'site_variant' => $template['site_variant'],
                'name' => $template['name'],
                'content_blocks' => json_encode($template['blocks']),
                'version' => 1,
            ];
            $divisionClause = $row['division_id'] === null ? 'is null' : '= ' . (int) $row['division_id'];
            $existing = $this->fetchRow(sprintf(
                "select id, edited_at from script_templates where sport_id = %d and division_id %s and site_variant = '%s'",
                $row['sport_id'],
                $divisionClause,
                $row['site_variant']
            ));

            if ($existing === false) {
                $data[] = $row;
                continue;
            }
            if ($existing['edited_at'] !== null) {
                continue;
            }
            $connection
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
}
