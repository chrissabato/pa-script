<?php

declare(strict_types=1);

namespace Tests\Roster;

use App\Roster\RosterUrlGuesser;
use PHPUnit\Framework\TestCase;

class RosterUrlGuesserTest extends TestCase
{
    /** @var RosterUrlGuesser */
    private $guesser;

    protected function setUp(): void
    {
        $this->guesser = new RosterUrlGuesser();
    }

    public function testSidearmUsesDivisionPrefixedSportSlug(): void
    {
        self::assertSame(
            'https://golcathletics.com/sports/womens-volleyball/roster',
            $this->guesser->guess('https://golcathletics.com/', 'sidearm', 'volleyball', 'womens', '2026-10-08')
        );
    }

    public function testSidearmDivisionlessSport(): void
    {
        self::assertSame(
            'https://golutes.com/sports/baseball/roster',
            $this->guesser->guess('https://golutes.com', 'sidearm', 'baseball', null, '2026-10-08')
        );
    }

    public function testPrestoUsesSportCodeAndAcademicYear(): void
    {
        self::assertSame(
            'https://wubearcats.com/sports/wvball/2026-27/roster',
            $this->guesser->guess('https://wubearcats.com', 'presto', 'volleyball', 'womens', '2026-10-08')
        );
        self::assertSame(
            'https://wubearcats.com/sports/bsb/2025-26/roster',
            $this->guesser->guess('https://wubearcats.com', 'presto', 'baseball', null, '2026-03-14')
        );
    }

    public function testUnknownProviderOrMissingSiteGivesNoGuess(): void
    {
        self::assertNull($this->guesser->guess('https://athletics.augsburg.edu', null, 'soccer', 'mens', '2026-10-08'));
        self::assertNull($this->guesser->guess(null, 'sidearm', 'soccer', 'mens', '2026-10-08'));
        self::assertNull($this->guesser->guess('https://wubearcats.com', 'presto', 'hockey', 'mens', '2026-10-08'));
        // Gendered sport with no division: which team's roster is unknowable.
        self::assertNull($this->guesser->guess('https://golcathletics.com', 'sidearm', 'volleyball', null, '2026-10-08'));
        self::assertNull($this->guesser->guess('https://wubearcats.com', 'presto', 'volleyball', null, '2026-10-08'));
    }

    public function testCurrentSeasonRollsPrestoSeasonForward(): void
    {
        self::assertSame(
            'https://wubearcats.com/sports/wvball/2026-27/roster',
            $this->guesser->currentSeason('https://wubearcats.com/sports/wvball/2025-26/roster', '2026-10-08')
        );
        self::assertSame(
            'https://golcathletics.com/sports/womens-volleyball/roster',
            $this->guesser->currentSeason('https://golcathletics.com/sports/womens-volleyball/roster', '2026-10-08')
        );
    }
}
