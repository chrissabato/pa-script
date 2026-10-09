<?php

declare(strict_types=1);

namespace Tests\Fill;

use App\Fill\DocumentFillException;
use App\Fill\DocumentText;
use PHPUnit\Framework\TestCase;

class DocumentTextTest extends TestCase
{
    public function testReadsParagraphsAndTableRowsFromADocx(): void
    {
        $text = DocumentText::fromDocx((string) file_get_contents(__DIR__ . '/../fixtures/documents/pronunciation-guide.docx'));

        $this->assertSame(
            "Linfield Wildcats & Pronunciation Guide\n"
            . "Head coach Jane Smith is in her fourth season.\n"
            . "No. | Name | Pronunciation\n"
            . "7 | Siobhan Nguyen | shi-VAWN WIN\n"
            . "Notes\tLine one\nLine two",
            $text
        );
    }

    public function testRejectsAFileThatIsNotADocx(): void
    {
        $this->expectException(DocumentFillException::class);
        DocumentText::fromDocx('not a zip at all');
    }
}
