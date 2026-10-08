<?php

declare(strict_types=1);

/**
 * Replays every saved roster page in tests/fixtures/roster-pages/ through
 * public/js/scraper-core.js in headless Chrome — the same DOMParser path
 * the roster page's "Fetch roster" button uses — and prints what each
 * adapter extracted. Dev-box only (needs google-chrome); not part of the
 * PHPUnit suite.
 *
 *   php tests/scraper/run.php [fixture-name ...] [--json]
 */

$root = dirname(__DIR__, 2);
$chrome = getenv('CHROME') ?: 'google-chrome';
$core = file_get_contents($root . '/public/js/scraper-core.js');

$args = array_slice($argv, 1);
$asJson = in_array('--json', $args, true);
$names = array_values(array_filter($args, function ($a) {
    return $a !== '--json';
}));

$fixtures = glob($root . '/tests/fixtures/roster-pages/*.html');
if ($names !== []) {
    $fixtures = array_filter($fixtures, function ($f) use ($names) {
        return in_array(basename($f, '.html'), $names, true);
    });
}

$tmp = sys_get_temp_dir() . '/pa-script-scraper-' . getmypid();
mkdir($tmp);

foreach ($fixtures as $fixture) {
    $harness = '<!doctype html><html><body><pre id="out"></pre><script>' . $core . '</script><script>'
        . 'var d = new DOMParser().parseFromString(' . json_encode(file_get_contents($fixture)) . ', "text/html");'
        // Fixtures are scraped as if served from a /sports/wvball/ roster URL,
        // so coachesPageUrl() has a sport to match; *coaches-page* fixtures
        // are a sport's separate coaches page (legacy Presto).
        . 'var url = "https://example.edu/sports/wvball/2026-27/roster";'
        . 'var r = PaScriptScrape.run(d, url);'
        . 'r.coachesPage = PaScriptScrape.coachesPageUrl(d, url);'
        . (strpos(basename($fixture), 'coaches-page') !== false ? 'r.coaches = PaScriptScrape.coachesFromPage(d);' : '')
        . 'document.getElementById("out").textContent = JSON.stringify(r);'
        . '</script></body></html>';
    $page = $tmp . '/harness.html';
    file_put_contents($page, $harness);

    $dom = shell_exec(escapeshellarg($chrome) . ' --headless --disable-gpu --no-sandbox --dump-dom '
        . escapeshellarg('file://' . $page) . ' 2>/dev/null');
    if (!preg_match('#<pre id="out">(.*?)</pre>#s', (string) $dom, $m)) {
        echo basename($fixture) . ": no output from Chrome\n";
        continue;
    }
    $result = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);

    echo '== ' . basename($fixture) . ' — via ' . $result['scrapedVia'] . ', ' . count($result['players']) . ' players, '
        . count($result['coaches']) . " coaches\n";
    if ($asJson) {
        $out = ['players' => $result['players'], 'coaches' => $result['coaches']];
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        continue;
    }
    if ($result['coachesPage'] !== null) {
        echo '   coaches page: ' . $result['coachesPage'] . "\n";
    }
    foreach ($result['coaches'] as $c) {
        printf("   coach: %-26s | %s\n", $c['name'], $c['title']);
    }
    foreach (array_slice($result['players'], 0, 4) as $p) {
        printf("   #%-3s %-26s | %-18s | %-12s | %s\n", $p['number'], $p['name'], $p['position'], $p['year'], $p['hometown']);
    }
    if ($result['players'] === []) {
        echo '   raw text: ' . substr($result['rawText'], 0, 80) . "\n";
    }
}

array_map('unlink', glob($tmp . '/*'));
rmdir($tmp);
