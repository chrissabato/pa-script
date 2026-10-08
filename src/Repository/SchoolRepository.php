<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class SchoolRepository
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
        $stmt = $this->pdo->query('select * from schools order by name');

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('select * from schools where id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('select * from schools where name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'insert into schools (name, short_name, mascot, logo, color, url, cms_provider) '
            . 'values (:name, :short_name, :mascot, :logo, :color, :url, :cms_provider) returning id'
        );
        $stmt->execute($this->normalize($data));

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'update schools set name = :name, short_name = :short_name, mascot = :mascot, logo = :logo, '
            . 'color = :color, url = :url, cms_provider = :cms_provider, updated_at = current_timestamp '
            . 'where id = :id'
        );
        $stmt->execute(array_merge(['id' => $id], $this->normalize($data)));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string|null>
     */
    private function normalize(array $data): array
    {
        $clean = function (string $key) use ($data): ?string {
            $value = trim((string) ($data[$key] ?? ''));

            return $value !== '' ? $value : null;
        };

        $color = $clean('color');
        if ($color !== null) {
            $color = strtoupper($color[0] === '#' ? $color : '#' . $color);
            if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                $color = null;
            }
        }

        // Accept a bare domain ("wubearcats.com") the way teams.json stores them.
        $url = $clean('url');
        if ($url !== null && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return [
            'name' => (string) $clean('name'),
            'short_name' => $clean('short_name'),
            'mascot' => $clean('mascot'),
            'logo' => $clean('logo'),
            'color' => $color,
            'url' => $url,
            'cms_provider' => $clean('cms_provider'),
        ];
    }

    public function findOrCreateByName(string $name, ?string $mascot = null): array
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->create(['name' => $name, 'mascot' => $mascot]);

        return $this->find($id);
    }
}
