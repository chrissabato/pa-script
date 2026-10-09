<?php

declare(strict_types=1);

namespace App\Database;

/**
 * For Phinx seeders (AbstractSeed) of small lookup tables with a unique
 * slug: re-running the seeder updates rows instead of failing on them.
 */
trait UpsertsBySlug
{
    /**
     * Insert rows whose slug is missing, update the rest in place, so the
     * seeder is safe to re-run (slug is unique).
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertBySlug(string $table, array $rows): void
    {
        $new = [];
        foreach ($rows as $row) {
            $existing = $this->fetchRow(sprintf(
                "select id from %s where slug = %s",
                $table,
                $this->getAdapter()->getConnection()->quote($row['slug'])
            ));
            if ($existing === false) {
                $new[] = $row;
                continue;
            }
            $sets = [];
            $params = ['id' => $existing['id']];
            foreach ($row as $column => $value) {
                $sets[] = $column . ' = :' . $column;
                $params[$column] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
            $this->getAdapter()->getConnection()
                ->prepare(sprintf('update %s set %s where id = :id', $table, implode(', ', $sets)))
                ->execute($params);
        }

        if ($new !== []) {
            $this->table($table)->insert($new)->saveData();
        }
    }
}
