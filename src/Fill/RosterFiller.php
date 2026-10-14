<?php

declare(strict_types=1);

namespace App\Fill;

use App\Rendering\Spoken;

/**
 * Asks Gemini to check a roster against an uploaded document (game notes, a
 * pronunciation guide, a media guide): it finds the details the scraped
 * roster page didn't have — mostly pronunciations and coaches' season
 * counts — and the ones where the document disagrees with the roster (a
 * different jersey number, spelling, class year, ...).
 *
 * Nothing is saved here: the suggestions go to a review page, and only the
 * ones a person ticks are written (GameRosterRepository::fillPlayerField /
 * fillCoachField, which only change a field still holding the value the
 * review page showed).
 */
class RosterFiller
{
    public const PLAYER_FIELDS = ['number', 'name', 'pronunciation', 'position', 'year', 'hometown'];
    public const COACH_FIELDS = ['name', 'pronunciation', 'pronoun', 'seasons'];

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
     * @param string $sportSlug for comparing positions ("F" and "Forward" agree)
     * @param array<int, array<string, mixed>> $players game_roster_players rows
     * @param array<int, array<string, mixed>> $coaches game_roster_coaches rows
     * @return array{players: array<int, array<string, mixed>>, coaches: array<int, array<string, mixed>>}
     *         suggestions: ['kind', 'id', 'label', 'field', 'current', 'value', 'evidence'] per
     *         field the document fills in (current null) or disagrees with (current = the roster's value)
     * @throws DocumentFillException
     */
    public function suggest(
        string $filename,
        string $bytes,
        string $teamName,
        string $sportSlug,
        array $players,
        array $coaches
    ): array {
        if ($players === [] && $coaches === []) {
            throw new DocumentFillException('This roster has no players or coaches yet — import or add them first.');
        }

        $parts = [$this->documentPart($filename, $bytes), ['text' => self::prompt($teamName, $players, $coaches)]];
        $answer = $this->model->generateJson($parts, self::schema());

        return [
            'players' => self::collect($answer['players'] ?? [], $players, self::PLAYER_FIELDS, 'player', $sportSlug),
            'coaches' => self::collect($answer['coaches'] ?? [], $coaches, self::COACH_FIELDS, 'coach', $sportSlug),
        ];
    }

    /**
     * Whether two values of a field say the same thing, ignoring style:
     * "Sr." / "Senior", "Bend, Ore." / "Bend, OR", "F" / "Forward",
     * case, spacing and punctuation.
     *
     * @param mixed $a
     * @param mixed $b
     */
    public static function agrees(string $field, $a, $b, string $sportSlug): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        switch ($field) {
            case 'number':
                return ltrim($a, '#') === ltrim($b, '#');
            case 'seasons':
                return (int) $a === (int) $b;
            case 'year':
                return self::sameWords(Spoken::year($a), Spoken::year($b));
            case 'hometown':
                return self::sameWords(Spoken::hometown($a), Spoken::hometown($b));
            case 'position':
                $spoken = function (string $position) use ($sportSlug): string {
                    return implode('/', array_map(function (string $part) use ($sportSlug): string {
                        return Spoken::position($part, $sportSlug);
                    }, explode('/', $position)));
                };

                return self::sameWords($spoken($a), $spoken($b));
            default:
                return self::sameWords($a, $b);
        }
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
        if ($field === 'number') {
            $value = ltrim($value, '# ');

            return $value !== '' && strlen($value) <= 10 ? $value : null;
        }
        if ($field === 'name' && preg_match('/\d/', $value)) {
            return null; // the model mangling accents ("Barr3n N33ez"), not a real spelling
        }
        if ($field === 'seasons') {
            return ctype_digit($value) && (int) $value > 0 && (int) $value < 100 ? (int) $value : null;
        }
        if ($field === 'pronoun') {
            $value = strtolower($value);

            return in_array($value, ['her', 'his', 'their'], true) ? $value : null;
        }

        // An overlong value is the model running on, not a value to cut short.
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        return $length <= ($field === 'year' ? 20 : 255) ? $value : null;
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
                return self::pick($p, array_merge(['id'], self::PLAYER_FIELDS));
            }, $players),
            'coaches' => array_map(function (array $c): array {
                return self::pick($c, array_merge(['id', 'title'], self::COACH_FIELDS));
            }, $coaches),
        ];
        $rosterJson = json_encode($roster, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are helping a college sports public-address announcer prepare for a game.
The document above (game notes, a pronunciation guide, or a media guide) is
about {$teamName}. Below is that team's roster as currently entered, as JSON;
a null field is missing.

For each player and coach on the roster, report what the document says about
them, for every field below — whether the roster has it or not, so missing
values can be filled in and disagreements with the roster spotted:
- number, name: the jersey number and the name as the document gives them.
- pronunciation: a phonetic pronunciation guide for the person's name, copied
  exactly as the document writes it (e.g. "kuh-NOR WAH-luhs"), including
  hints like "(rhymes with tree)". Leave out pronoun notes such as "(She/Her)".
  If the guide just repeats the name as spelled, there's no pronunciation to
  give. Never make one up or guess one from the spelling.
- position, year (class year, e.g. "Sr." or "Junior"), hometown ("City, State"
  as written, without the high school) for players.
- seasons for coaches: which season with this team they are in now, as a
  whole number (a coach "in their fourth season" or "entering year four" is 4;
  "has coached here since" a year means count the seasons through the current
  one only if the document makes it unambiguous).
- pronoun for coaches: "her", "his" or "their", only if the document refers to
  the coach with one.

Match each document entry to a roster person by name and jersey number; a
person whose number (or name spelling) differs in the document still matches
when the rest makes it clear it's the same person — report the document's
number or spelling so the difference shows. If you aren't sure the document
means the same person, leave them out. Return each person by their roster
"id". Only give values the document actually states; use "" for anything it
doesn't. For each person give "evidence": the short phrase from the document
you took the values from (for a pronunciation, the pronunciation-guide line,
not the roster line).

ROSTER:
{$rosterJson}
PROMPT;
    }

    /** @return array<string, mixed> Gemini responseSchema (OpenAPI subset) */
    private static function schema(): array
    {
        $person = function (array $fields): array {
            $properties = ['id' => ['type' => 'INTEGER']];
            foreach ($fields as $field) {
                $properties[$field] = ['type' => 'STRING'];
            }
            $properties['evidence'] = ['type' => 'STRING'];

            // Gemini otherwise writes the fields alphabetically.
            return [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => $properties,
                    'propertyOrdering' => array_keys($properties),
                    'required' => ['id'],
                ],
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
     * roster and values that clean up and either fill a blank field or
     * disagree with the roster's value.
     *
     * @param mixed $found
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $fields
     * @return array<int, array<string, mixed>>
     */
    private static function collect($found, array $rows, array $fields, string $kind, string $sportSlug): array
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
                $current = self::isBlank($row[$field] ?? null) ? null : $row[$field];
                if ($value === null || ($current !== null && self::agrees($field, $current, $value, $sportSlug))) {
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
                    'current' => $current === null ? null : (string) $current,
                    'value' => $value,
                    'evidence' => trim((string) ($item['evidence'] ?? '')),
                ];
            }
        }

        return $suggestions;
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
