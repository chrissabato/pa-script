<?php

use App\Database\Config;
use App\Database\Connection;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = Config::fromEnv($_ENV);

$environment = [
    'adapter' => 'pgsql',
    'host' => $db['host'],
    'name' => $db['name'],
    'user' => $db['user'],
    'pass' => $db['pass'],
    'port' => $db['port'],
    'charset' => 'utf8',
];

// Phinx needs the schema to exist before it can migrate into it (phinxlog
// included), so create it on first run.
if ($db['schema'] !== '') {
    Connection::fromEnv()->exec('CREATE SCHEMA IF NOT EXISTS ' . Connection::quoteIdentifier($db['schema']));
    $environment['schema'] = $db['schema'];
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/../migrations',
        'seeds' => __DIR__ . '/../seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => $environment,
    ],
];
