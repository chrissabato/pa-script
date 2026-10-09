<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\Abbreviate;
use PHPUnit\Framework\TestCase;

class AbbreviateTest extends TestCase
{
    public function testYear(): void
    {
        self::assertSame('Jr.', Abbreviate::year('Junior'));
        self::assertSame('Jr.', Abbreviate::year('jr'));
        self::assertSame('Jr.', Abbreviate::year('Jr.'));
        self::assertSame('So.', Abbreviate::year('Sophomore'));
        self::assertSame('So.', Abbreviate::year('Sophmore'));   // real typo on a scraped roster
        self::assertSame('Sr.', Abbreviate::year('Senior'));
        self::assertSame('Fy.', Abbreviate::year('First Year'));
        self::assertSame('Fy.', Abbreviate::year('Freshman'));
        self::assertSame('Fy.', Abbreviate::year('Fy'));
        self::assertSame('Gr.', Abbreviate::year('Graduate'));
        self::assertSame('Redshirt Junior', Abbreviate::year('Redshirt Junior')); // unknown: as-is
        self::assertSame('', Abbreviate::year(null));
    }

    public function testHometownState(): void
    {
        self::assertSame('Portland, Ore.', Abbreviate::hometown('Portland, Oregon'));
        self::assertSame('Portland, Ore.', Abbreviate::hometown('Portland, OR'));
        self::assertSame('Portland, Ore.', Abbreviate::hometown('Portland, Ore.'));
        self::assertSame('Burien, Wash.', Abbreviate::hometown('Burien, Washington'));
        self::assertSame('Vacaville, Calif.', Abbreviate::hometown('Vacaville, CA'));
        self::assertSame('Albuquerque, N.M.', Abbreviate::hometown('Albuquerque, New Mexico'));
        // AP spells out Hawaii, Idaho, Texas, ...; countries are untouched.
        self::assertSame('Hilo, Hawaii', Abbreviate::hometown('Hilo, Hawaii'));
        self::assertSame('Hayden Lake, Idaho', Abbreviate::hometown('Hayden Lake, Idaho'));
        self::assertSame('Montevideo, Uruguay', Abbreviate::hometown('Montevideo, Uruguay'));
        self::assertSame('Buckeye', Abbreviate::hometown('Buckeye'));
    }
}
