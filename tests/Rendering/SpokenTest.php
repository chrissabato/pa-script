<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\Spoken;
use PHPUnit\Framework\TestCase;

class SpokenTest extends TestCase
{
    public function testNameDisplayModes(): void
    {
        $guided = ['name' => 'Siera Edwards', 'pronunciation' => 'SEE-era ED-werds'];
        $plain = ['name' => 'Brie Maker', 'pronunciation' => null];
        $same = ['name' => 'Coco Otto', 'pronunciation' => 'coco otto'];

        self::assertSame('Siera Edwards', Spoken::name($guided, 'name'));
        self::assertSame('SEE-era ED-werds', Spoken::name($guided, 'pronunciation'));
        self::assertSame('Siera Edwards (SEE-era ED-werds)', Spoken::name($guided, 'both'));

        // No guide: every mode falls back to the name.
        foreach (array_keys(Spoken::NAME_DISPLAYS) as $mode) {
            self::assertSame('Brie Maker', Spoken::name($plain, $mode));
        }
        // A guide that just repeats the name isn't shown twice.
        self::assertSame('Coco Otto', Spoken::name($same, 'both'));
    }

    public function testPositionYearHometownOrdinal(): void
    {
        self::assertSame('goalie', Spoken::position('GK', 'soccer'));
        self::assertSame('forward', Spoken::position('F/M', 'soccer'));
        self::assertSame('defensive specialist', Spoken::position('DS/L', 'volleyball'));
        self::assertSame('libero', Spoken::position('L', 'volleyball'));
        self::assertSame('xyz', Spoken::position('XYZ', 'soccer'));
        self::assertSame('first year', Spoken::year('Fy.'));
        self::assertSame('first year', Spoken::year('Fr.'));
        self::assertSame('Issaquah, Washington', Spoken::hometown('Issaquah, Wash.'));
        self::assertSame('Albuquerque, New Mexico', Spoken::hometown('Albuquerque, N.M.'));
        self::assertSame('Montevideo, Uruguay', Spoken::hometown('Montevideo, Uruguay'));
        self::assertSame('17th', Spoken::ordinal(17));
        self::assertSame('22nd', Spoken::ordinal(22));
        self::assertSame('11th', Spoken::ordinal(11));
    }
}
