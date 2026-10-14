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

    public function testSuggestsBlankFieldsAndDifferencesForPeopleOnTheRoster(): void
    {
        $model = new FakeModel([
            'players' => [
                ['id' => 11, 'number' => '7', 'name' => 'Siobhan Nguyen', 'pronunciation' => ' shi-VAWN  WIN ', 'position' => 'D', 'year' => '',
                    'evidence' => '7 | Siobhan Nguyen | shi-VAWN WIN'],
                // Same in other words (agrees), except the number and the pronunciation.
                ['id' => 12, 'number' => '#19', 'name' => 'ana ruiz', 'pronunciation' => 'AH-nah', 'position' => 'Midfielder',
                    'year' => 'Senior', 'hometown' => 'Bend, OR'],
                ['id' => 13, 'pronunciation' => 'Lia  Park', 'hometown' => 'Salem, Ore.'], // just the name: dropped
                ['id' => 99, 'pronunciation' => 'nobody'],   // not on this roster
            ],
            'coaches' => [
                ['id' => 3, 'name' => 'Jane Smith', 'pronoun' => 'Her', 'seasons' => '4', 'pronunciation' => '', 'evidence' => 'in her fourth season'],
            ],
        ]);

        $result = (new RosterFiller($model))->suggest('guide.docx', $this->docx(), 'Linfield', 'soccer', $this->players, $this->coaches);

        $this->assertSame([
            ['kind' => 'player', 'id' => 11, 'label' => '#7 Siobhan Nguyen', 'field' => 'position', 'current' => 'F', 'value' => 'D',
                'evidence' => '7 | Siobhan Nguyen | shi-VAWN WIN'],
            ['kind' => 'player', 'id' => 11, 'label' => '#7 Siobhan Nguyen', 'field' => 'pronunciation', 'current' => null, 'value' => 'shi-VAWN WIN',
                'evidence' => '7 | Siobhan Nguyen | shi-VAWN WIN'],
            ['kind' => 'player', 'id' => 12, 'label' => '#9 Ana Ruiz', 'field' => 'number', 'current' => '9', 'value' => '19', 'evidence' => ''],
            ['kind' => 'player', 'id' => 12, 'label' => '#9 Ana Ruiz', 'field' => 'pronunciation', 'current' => 'AH-nuh', 'value' => 'AH-nah', 'evidence' => ''],
            ['kind' => 'player', 'id' => 13, 'label' => 'Lia Park', 'field' => 'hometown', 'current' => null, 'value' => 'Salem, Ore.', 'evidence' => ''],
        ], $this->sorted($result['players']));
        $this->assertSame([
            ['kind' => 'coach', 'id' => 3, 'label' => 'Jane Smith', 'field' => 'pronoun', 'current' => null, 'value' => 'her', 'evidence' => 'in her fourth season'],
            ['kind' => 'coach', 'id' => 3, 'label' => 'Jane Smith', 'field' => 'seasons', 'current' => null, 'value' => 4, 'evidence' => 'in her fourth season'],
        ], $result['coaches']);

        // The Word file went to the model as text, followed by the prompt with the roster.
        $this->assertStringContainsString('shi-VAWN WIN', $model->parts[0]['text']);
        $this->assertStringContainsString('"name": "Siobhan Nguyen"', $model->parts[1]['text']);
    }

    public function testAgreesIgnoresStyle(): void
    {
        $this->assertTrue(RosterFiller::agrees('year', 'Fr.', 'Freshman', 'soccer'));
        $this->assertTrue(RosterFiller::agrees('hometown', 'Bend, Ore.', 'Bend, Oregon', 'soccer'));
        $this->assertTrue(RosterFiller::agrees('position', 'GK', 'Goalkeeper', 'soccer'));
        $this->assertTrue(RosterFiller::agrees('number', '#4', '4', 'soccer'));
        $this->assertTrue(RosterFiller::agrees('name', 'Mary-Kate Smith', 'mary kate smith', 'soccer'));
        $this->assertFalse(RosterFiller::agrees('number', '04', '4', 'soccer'));
        $this->assertFalse(RosterFiller::agrees('year', 'Jr.', 'Sr.', 'soccer'));
    }

    public function testRefusesAnEmptyRoster(): void
    {
        $this->expectException(DocumentFillException::class);
        (new RosterFiller(new FakeModel([])))->suggest('notes.pdf', '%PDF', 'Linfield', 'soccer', [], []);
    }

    public function testSendsAPdfAsInlineData(): void
    {
        $model = new FakeModel(['players' => [], 'coaches' => []]);
        (new RosterFiller($model))->suggest('notes.pdf', '%PDF-1.4 fake', 'Linfield', 'soccer', $this->players, $this->coaches);

        $this->assertSame('application/pdf', $model->parts[0]['inline_data']['mime_type']);
        $this->assertSame(base64_encode('%PDF-1.4 fake'), $model->parts[0]['inline_data']['data']);
    }

    public function testRefusesOldWordFiles(): void
    {
        $this->expectException(DocumentFillException::class);
        $this->expectExceptionMessageMatches('/\.docx or PDF/');
        (new RosterFiller(new FakeModel([])))->suggest('notes.doc', 'x', 'Linfield', 'soccer', $this->players, $this->coaches);
    }

    public function testCleansValuesPerField(): void
    {
        $this->assertSame(4, RosterFiller::clean('seasons', ' 4 '));
        $this->assertNull(RosterFiller::clean('seasons', 'fourth'));
        $this->assertNull(RosterFiller::clean('pronoun', 'she'));
        $this->assertSame('their', RosterFiller::clean('pronoun', 'Their'));
        $this->assertNull(RosterFiller::clean('pronunciation', '   '));
        $this->assertNull(RosterFiller::clean('year', 'Fy. Phonetics for Lucia Barrón'));
        $this->assertSame('12', RosterFiller::clean('number', '#12'));
        $this->assertNull(RosterFiller::clean('name', 'Lucia Barr3n N33ez'));
    }

    /**
     * @param array<int, array<string, mixed>> $suggestions
     * @return array<int, array<string, mixed>> by id, then field
     */
    private function sorted(array $suggestions): array
    {
        usort($suggestions, function (array $a, array $b): int {
            return [$a['id'], $a['field']] <=> [$b['id'], $b['field']];
        });

        return $suggestions;
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
