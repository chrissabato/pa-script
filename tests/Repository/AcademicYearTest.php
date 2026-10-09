<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\GameRosterRepository;
use PHPUnit\Framework\TestCase;

class AcademicYearTest extends TestCase
{
    public function testFallDateStartsThatAugust(): void
    {
        $this->assertSame(['2026-08-01', '2027-07-31'], GameRosterRepository::academicYear('2026-10-08'));
        $this->assertSame(['2026-08-01', '2027-07-31'], GameRosterRepository::academicYear('2026-08-01'));
    }

    public function testSpringDateBelongsToThePreviousAugust(): void
    {
        $this->assertSame(['2026-08-01', '2027-07-31'], GameRosterRepository::academicYear('2027-03-14'));
        $this->assertSame(['2025-08-01', '2026-07-31'], GameRosterRepository::academicYear('2026-07-31'));
    }
}
