<?php

declare(strict_types=1);

namespace Tests\Rendering;

use App\Rendering\GameContextBuilder;
use PHPUnit\Framework\TestCase;

class GameContextBuilderTest extends TestCase
{
    private static function meta(array $game): array
    {
        $empty = ['players' => [], 'coaches' => []];

        return GameContextBuilder::assemble($game, ['home' => $empty, 'away' => $empty], [])['meta'];
    }

    public function testVenueFallsBackToTheSportsDefaultAtHomeOnly(): void
    {
        $game = ['sport_slug' => 'soccer', 'sport_default_venue' => 'Sparks Field'];

        $this->assertSame('Sparks Field', self::meta($game)['venue']);
        $this->assertSame('', self::meta($game)['gameVenue']);
        $this->assertSame('McCulloch Stadium', self::meta($game + ['venue' => ' McCulloch Stadium '])['venue']);
        $this->assertSame('', self::meta($game + ['is_neutral_site' => true])['venue']);
    }

    public function testTeamNameAsTheNewGameFormNamesIt(): void
    {
        $this->assertSame("Women's Soccer", self::meta(['sport_slug' => 'soccer', 'division_slug' => 'womens'])['teamName']);
        $this->assertSame('Softball', self::meta(['sport_slug' => 'softball', 'division_slug' => null])['teamName']);
        $this->assertSame("Men's Lacrosse", self::meta([
            'sport_slug' => 'lacrosse', 'division_slug' => 'mens', 'division_name' => "Men's", 'sport_name' => 'Lacrosse',
        ])['teamName']);
        $this->assertSame('volleyball', self::meta(['sport_slug' => 'volleyball'])['sport']);
    }
}
