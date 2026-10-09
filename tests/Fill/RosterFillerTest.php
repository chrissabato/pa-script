<?php

declare(strict_types=1);

namespace Tests\Fill;

use App\Fill\DocumentFillException;
use App\Fill\JsonModel;
use App\Fill\RosterFiller;
use PHPUnit\Framework\TestCase;

class RosterFillerTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private $players = [
        ['id' => 11, 'number' => '7', 'name' => 'Siobhan Nguyen', 'pronunciation' => null, 'position' => 'F', 'year' => null, 'hometown' => ''],
        ['id' => 12, 'number' => '9', 'name' => 'Ana Ruiz', 'pronunciation' => 'AH-nuh', 'position' => 'M', 'year' => 'Sr.', 'hometown' => 'Bend, Ore.'],
        ['id' => 13, 'number' => '', 'name' => 'Lia Park', 'pronunciation' => null, 'position' => 'D', 'year' => 'Jr.', 'hometown' => null],
    ];

    /** @var array<int, array<string, mixed>> */
    private $coaches = [
        ['id' => 3, 'name' => 'Jane Smith', 'title' => 'Head Coach', 'pronunciation' => null, 'pronoun' => null, 'seasons' => null],
    ];

    public function testSuggestsOnlyBlankFieldsOfPeopleOnTheRoster(): void
    {
        $model = new FakeModel([
            'players' => [
                ['id' => 11, 'pronunciation' => ' shi-VAWN  WIN ', 'position' => 'D', 'year' => '', 'evidence' => '7 | Siobhan Nguyen | shi-VAWN WIN'],
                ['id' => 12, 'pronunciation' => 'AH-nah'],   // already set: not suggested
                ['id' => 13, 'pronunciation' => 'Lia  Park', 'hometown' => 'Salem, Ore.'], // just the name: dropped
                ['id' => 99, 'pronunciation' => 'nobody'],   // not on this roster
            ],
            'coaches' => [
                ['id' => 3, 'pronoun' => 'Her', 'seasons' => '4', 'pronunciation' => '', 'evidence' => 'in her fourth season'],
            ],
        ]);

        $result = (new RosterFiller($model))->suggest('guide.docx', $this->docx(), 'Linfield', $this->players, $this->coaches);

        $this->assertSame([
            ['kind' => 'player', 'id' => 11, 'label' => '#7 Siobhan Nguyen', 'field' => 'pronunciation', 'value' => 'shi-VAWN WIN', 'evidence' => '7 | Siobhan Nguyen | shi-VAWN WIN'],
            ['kind' => 'player', 'id' => 13, 'label' => 'Lia Park', 'field' => 'hometown', 'value' => 'Salem, Ore.', 'evidence' => ''],
        ], $result['players']);
        $this->assertSame([
            ['kind' => 'coach', 'id' => 3, 'label' => 'Jane Smith', 'field' => 'pronoun', 'value' => 'her', 'evidence' => 'in her fourth season'],
            ['kind' => 'coach', 'id' => 3, 'label' => 'Jane Smith', 'field' => 'seasons', 'value' => 4, 'evidence' => 'in her fourth season'],
        ], $result['coaches']);

        // The Word file went to the model as text, followed by the prompt with the roster.
        $this->assertStringContainsString('shi-VAWN WIN', $model->parts[0]['text']);
        $this->assertStringContainsString('"name": "Siobhan Nguyen"', $model->parts[1]['text']);
    }

    public function testSendsAPdfAsInlineData(): void
    {
        $model = new FakeModel(['players' => [], 'coaches' => []]);
        (new RosterFiller($model))->suggest('notes.pdf', '%PDF-1.4 fake', 'Linfield', $this->players, $this->coaches);

        $this->assertSame('application/pdf', $model->parts[0]['inline_data']['mime_type']);
        $this->assertSame(base64_encode('%PDF-1.4 fake'), $model->parts[0]['inline_data']['data']);
    }

    public function testRefusesOldWordFiles(): void
    {
        $this->expectException(DocumentFillException::class);
        $this->expectExceptionMessageMatches('/\.docx or PDF/');
        (new RosterFiller(new FakeModel([])))->suggest('notes.doc', 'x', 'Linfield', $this->players, $this->coaches);
    }

    public function testCleansValuesPerField(): void
    {
        $this->assertSame(4, RosterFiller::clean('seasons', ' 4 '));
        $this->assertNull(RosterFiller::clean('seasons', 'fourth'));
        $this->assertNull(RosterFiller::clean('pronoun', 'she'));
        $this->assertSame('their', RosterFiller::clean('pronoun', 'Their'));
        $this->assertNull(RosterFiller::clean('pronunciation', '   '));
    }

    private function docx(): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/documents/pronunciation-guide.docx');
    }
}

class FakeModel implements JsonModel
{
    /** @var array<string, mixed> */
    private $answer;
    /** @var array<int, array<string, mixed>> */
    public $parts = [];

    /** @param array<string, mixed> $answer */
    public function __construct(array $answer)
    {
        $this->answer = $answer;
    }

    public function generateJson(array $parts, array $schema): array
    {
        $this->parts = $parts;

        return $this->answer;
    }
}
