<?php

declare(strict_types=1);

// Refresh the local `schools` cache from the athletics Team Data API.
// Usage: php bin/sync-teams.php   (safe to run from cron)

use App\Database\Connection;
use App\Site;
use App\Teams\TeamApiSync;

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

try {
    $result = (new TeamApiSync(Connection::fromEnv(), Site::teamsApiUrl()))->sync();
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Team sync failed: ' . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "Teams synced: %d updated, %d added, %d removed.\n",
    $result['updated'],
    $result['inserted'],
    $result['removed']
);
if ($result['kept'] !== []) {
    echo 'Not in the API but kept (used by games/rosters): ' . implode(', ', $result['kept']) . "\n";
}
