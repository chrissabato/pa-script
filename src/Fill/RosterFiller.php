<?php

declare(strict_types=1);

namespace App\Fill;

/**
 * Asks Gemini to find, in an uploaded document (game notes, a pronunciation
 * guide, a media guide), the roster details the scraped roster page didn't
 * have — mostly pronunciations and coaches' season counts.
 *
 * Only blank fields are suggested, and nothing is saved here: the
 * suggestions go to a review page, and only the ones a person ticks are
 * written (GameRosterRepository::fillPlayerField / fillCoachField, which
 * also refuse to overwrite a value).
 */
class RosterFiller
{
    public const PLAYER_FIELDS = ['pronunciation', 'position', 'year', 'hometown'];
    public const COACH_FIELDS = ['pronunciation', 'pronoun', 'seasons'];

    /** Upload size cap; Gemini's inline-data request limit is 20 MB after base64. */
    public const MAX_BYTES = 12 * 1024 * 1024;

    /** @var JsonModel */
    private $model;

    public function __construct(JsonModel $model)
    {
        $this->model = $model;
    }

    /**
     * @param string $filename the uploaded name; its extension picks PDF / Word / text
     * @param array<int, array<string, mixed>> $players game_roster_players rows
     * @param array<int, array<string, mixed>> $coaches game_roster_coaches rows
     * @return array{players: array<int, array<string, mixed>>, coaches: array<int, array<string, mixed>>}
     *         suggestions: ['id', 'label', 'field', 'value', 'evidence'] per blank field found
     * @throws DocumentFillException
     */
    public function suggest(string $filename, string $bytes, string $teamName, array $players, array $coaches): array
    {
        if ($this->blankFields($players, self::PLAYER_FIELDS) === 0 && $this->blankFields($coaches, self::COACH_FIELDS) === 0) {
            throw new DocumentFillException('Nothing to fill in: every player and coach field on this roster already has a value.');
        }

        $parts = [$this->documentPart($filename, $bytes), ['text' => self::prompt($teamName, $players, $coaches)]];
        $answer = $this->model->generateJson($parts, self::schema());

        return [
            'players' => self::collect($answer['players'] ?? [], $players, self::PLAYER_FIELDS, 'player'),
            'coaches' => self::collect($answer['coaches'] ?? [], $coaches, self::COACH_FIELDS, 'coach'),
        ];
    }

    /**
     * The value to store for a field, or null if it isn't a usable value
     * (a non-number for seasons, an unknown pronoun).
     *
     * @return string|int|null
     */
    public static function clean(string $field, string $value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return null;
        }
        if ($field === 'seasons') {
            return ctype_digit($value) && (int) $value > 0 && (int) $value < 100 ? (int) $value : null;
        }
        if ($field === 'pronoun') {
            $value = strtolower($value);

            return in_array($value, ['her', 'his', 'their'], true) ? $value : null;
        }

        $max = $field === 'year' ? 20 : 255;

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    /** @return array<string, mixed> */
    private function documentPart(string $filename, string $bytes): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'pdf' || strncmp($bytes, '%PDF', 4) === 0) {
            return ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($bytes)]];
        }
        if ($extension === 'docx') {
            $text = DocumentText::fromDocx($bytes);
        } elseif (in_array($extension, ['txt', 'csv'], true)) {
            $text = $bytes;
        } elseif ($extension === 'doc') {
            throw new DocumentFillException('Old Word .doc files can\'t be read — open it in Word and save it as .docx or PDF.');
        } else {
            throw new DocumentFillException('Upload a PDF or a Word (.docx) file.');
        }
        if (trim($text) === '') {
            throw new DocumentFillException('That document has no text in it to read.');
        }

        return ['text' => "DOCUMENT:\n" . $text];
    }

    /**
     * @param array<int, array<string, mixed>> $players
     * @param array<int, array<string, mixed>> $coaches
     */
    private static function prompt(string $teamName, array $players, array $coaches): string
    {
        $roster = [
            'players' => array_map(function (array $p): array {
                return self::pick($p, array_merge(['id', 'number', 'name'], self::PLAYER_FIELDS));
            }, $players),
            'coaches' => array_map(function (array $c): array {
                return self::pick($c, array_merge(['id', 'name', 'title'], self::COACH_FIELDS));
            }, $coaches),
        ];
        $rosterJson = json_encode($roster, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are helping a college sports public-address announcer prepare for a game.
The document above (game notes, a pronunciation guide, or a media guide) is
about {$teamName}. Below is that team's roster as currently entered, as JSON;
a null field is missing.

For each player and coach on the roster, find in the document any of their
MISSING fields:
- pronunciation: a phonetic pronunciation guide for the person's name, copied
  exactly as the document writes it (e.g. "kuh-NOR WAH-luhs"), including
  hints like "(rhymes with tree)". Leave out pronoun notes such as "(She/Her)".
  If the guide just repeats the name as spelled, there's no pronunciation to
  give. Never make one up or guess one from the spelling.
- position, year (class year, e.g. "Sr." or "Junior"), hometown ("City, State"
  as written) for players.
- seasons for coaches: which season with this team they are in now, as a
  whole number (a coach "in their fourth season" or "entering year four" is 4;
  "has coached here since" a year means count the seasons through the current
  one only if the document makes it unambiguous).
- pronoun for coaches: "her", "his" or "their", only if the document refers to
  the coach with one.

Match people by jersey number and name together; if you aren't sure the
document means the same person, leave them out. Return each person by their
roster "id". Only include fields that are null on the roster and that the
document actually states; use "" for anything you didn't find. For each person
give "evidence": the short phrase from the document you took the values from
(for a pronunciation, the pronunciation-guide line, not the roster line).

ROSTER:
{$rosterJson}
PROMPT;
    }

    /** @return array<string, mixed> Gemini responseSchema (OpenAPI subset) */
    private static function schema(): array
    {
        $person = function (array $fields): array {
            $properties = ['id' => ['type' => 'INTEGER'], 'evidence' => ['type' => 'STRING']];
            foreach ($fields as $field) {
                $properties[$field] = ['type' => 'STRING'];
            }

            return [
                'type' => 'ARRAY',
                'items' => ['type' => 'OBJECT', 'properties' => $properties, 'required' => ['id']],
            ];
        };

        return [
            'type' => 'OBJECT',
            'properties' => ['players' => $person(self::PLAYER_FIELDS), 'coaches' => $person(self::COACH_FIELDS)],
            'required' => ['players', 'coaches'],
        ];
    }

    /**
     * Turns the model's answer into suggestions, keeping only ids on this
     * roster, fields that are blank there, and values that clean up.
     *
     * @param mixed $found
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $fields
     * @return array<int, array<string, mixed>>
     */
    private static function collect($found, array $rows, array $fields, string $kind): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $suggestions = [];
        foreach (is_array($found) ? $found : [] as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (!is_array($item) || !isset($byId[$id])) {
                continue;
            }
            $row = $byId[$id];
            foreach ($fields as $field) {
                $value = self::clean($field, (string) ($item[$field] ?? ''));
                if ($value === null || !self::isBlank($row[$field] ?? null)) {
                    continue;
                }
                // A guide that only repeats the name ("Iris Stutzman") adds nothing.
                if ($field === 'pronunciation' && self::sameWords((string) $value, (string) $row['name'])) {
                    continue;
                }
                $suggestions[] = [
                    'kind' => $kind,
                    'id' => $id,
                    'label' => $kind === 'player' && (string) $row['number'] !== ''
                        ? '#' . $row['number'] . ' ' . $row['name']
                        : (string) $row['name'],
                    'field' => $field,
                    'value' => $value,
                    'evidence' => trim((string) ($item['evidence'] ?? '')),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $fields
     */
    private function blankFields(array $rows, array $fields): int
    {
        $blank = 0;
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $blank += self::isBlank($row[$field] ?? null) ? 1 : 0;
            }
        }

        return $blank;
    }

    private static function sameWords(string $a, string $b): bool
    {
        $normalize = function (string $s): string {
            return strtolower(trim((string) preg_replace('/[^\pL\pN]+/u', ' ', $s)));
        };

        return $normalize($a) === $normalize($b);
    }

    /** @param mixed $value */
    private static function isBlank($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     * @return array<string, mixed>
     */
    private static function pick(array $row, array $keys): array
    {
        $picked = [];
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            $picked[$key] = self::isBlank($value) ? null : ($key === 'id' ? (int) $value : $value);
        }

        return $picked;
    }
}
