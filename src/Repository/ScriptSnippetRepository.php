<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Global script snippets: wording every template can use as [snippet Name]
 * (the welcome line, the sportsmanship statement, ...).
 */
class ScriptSnippetRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('select * from script_snippets order by name')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('select * from script_snippets where id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return string[] */
    public function names(): array
    {
        return $this->pdo->query('select name from script_snippets order by name')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Every snippet's content blocks by name, for TemplateRenderer.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function blocksByName(): array
    {
        $snippets = [];
        foreach ($this->all() as $row) {
            $snippets[$row['name']] = (array) json_decode($row['content_blocks'], true);
        }

        return $snippets;
    }

    /** A new, empty snippet; the name must be free (see names()). */
    public function create(string $name): int
    {
        $stmt = $this->pdo->prepare(
            "insert into script_snippets (name, content_blocks, edited_at) values (:name, '[]', current_timestamp) returning id"
        );
        $stmt->execute(['name' => $name]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Saves new content blocks. $edited marks a save from the editor
     * (ScriptTemplatesSeeder then leaves a default snippet alone); false is
     * a reset to the default wording.
     *
     * @param array<int, array<string, mixed>> $blocks
     */
    public function updateBlocks(int $id, array $blocks, bool $edited): void
    {
        $stmt = $this->pdo->prepare(
            'update script_snippets set content_blocks = :content_blocks, updated_at = current_timestamp, '
            . 'edited_at = ' . ($edited ? 'current_timestamp' : 'null') . ' where id = :id'
        );
        $stmt->execute(['content_blocks' => json_encode($blocks), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('delete from script_snippets where id = :id');
        $stmt->execute(['id' => $id]);
    }
}
