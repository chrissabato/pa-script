<?php

declare(strict_types=1);

use App\Database\PgType;
use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameRosterRepository;
use App\Repository\ImportBatchRepository;
use App\Repository\OfficialRepository;
use App\Repository\ProgramRepository;
use App\Repository\SchoolRepository;
use App\Repository\ScriptTemplateRepository;
use App\Repository\SportRepository;
use App\Roster\RosterFetchBlocked;
use App\Roster\RosterFetchException;
use App\Roster\RosterPageFetcher;
use App\Roster\RosterUrlGuesser;
use App\Teams\TeamApiSync;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

/** Refresh the local team cache from the API when it's older than this (seconds). */
const TEAM_SYNC_MAX_AGE = 3600;

/**
 * Person/team form fields are deliberately not called "name"/"title"
 * (player, coach, staff_role, official, school) so password managers
 * like LastPass don't treat them as identity fields. This maps a posted
 * player row back to the `name` key the repositories expect.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function playerFormData(array $data): array
{
    $data['name'] = (string) ($data['player'] ?? '');
    unset($data['player']);

    return $data;
}

return function (App $app, PDO $pdo, Renderer $view): void {
    $sports = new SportRepository($pdo);
    $divisions = new DivisionRepository($pdo);
    $schools = new SchoolRepository($pdo);
    $programs = new ProgramRepository($pdo);
    $games = new GameRepository($pdo);
    $gameRosters = new GameRosterRepository($pdo);
    $officials = new OfficialRepository($pdo);
    $scriptTemplates = new ScriptTemplateRepository($pdo);
    $importBatches = new ImportBatchRepository($pdo);
    $teamSync = new TeamApiSync($pdo);
    $contextBuilder = new GameContextBuilder($gameRosters, $officials);
    $renderer = new TemplateRenderer();
    $rosterUrlGuesser = new RosterUrlGuesser();
    $rosterFetcher = new RosterPageFetcher();

    // The roster-import panel's URL for a program: the saved one (rolled
    // forward to the current Presto season), else a guess from the
    // school's site + CMS provider. `isGuess` lets the page say so.
    $rosterImportUrl = function (array $program, string $onDate) use ($rosterUrlGuesser): array {
        $saved = trim((string) ($program['roster_url'] ?? ''));
        if ($saved !== '') {
            return ['url' => $rosterUrlGuesser->currentSeason($saved, $onDate), 'isGuess' => false];
        }

        return [
            'url' => $rosterUrlGuesser->guess(
                $program['school_url'] ?? null,
                $program['school_cms_provider'] ?? null,
                (string) $program['sport_slug'],
                $program['division_slug'] ?? null,
                $onDate
            ),
            'isGuess' => true,
        ];
    };

    // ---- Dashboard -------------------------------------------------------

    $app->get('/', function (Request $request, Response $response) use ($view, $games) {
        $html = $view->render('home', [
            'title' => 'PA Script — Dashboard',
            'games' => $games->onOrAfter(date('Y-m-d')),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    // ---- Schools (teams) -------------------------------------------------

    $app->get('/schools', function (Request $request, Response $response) use ($view, $schools, $teamSync) {
        $syncError = $teamSync->syncIfStale(TEAM_SYNC_MAX_AGE);
        $query = $request->getQueryParams();

        $html = $view->render('schools/index', [
            'title' => 'Teams',
            'schools' => $schools->all(),
            'lastSyncedAt' => $schools->lastSyncedAt(),
            'syncError' => $syncError ?? ($query['sync_error'] ?? null),
            'syncResult' => isset($query['synced']) ? $query : null,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/schools/sync', function (Request $request, Response $response) use ($teamSync) {
        try {
            $result = $teamSync->sync();
            $params = [
                'synced' => 1,
                'updated' => $result['updated'],
                'inserted' => $result['inserted'],
                'removed' => $result['removed'],
            ];
        } catch (\RuntimeException $e) {
            $params = ['sync_error' => $e->getMessage()];
        }

        return $response->withHeader('Location', '/schools?' . http_build_query($params))->withStatus(302);
    });

    // ---- Games ----------------------------------------------------------

    $app->get('/games', function (Request $request, Response $response) use ($view, $games) {
        $html = $view->render('games/index', [
            'title' => 'Games',
            'games' => $games->upcoming(),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/games/new', function (Request $request, Response $response) use (
        $view,
        $sports,
        $divisions,
        $schools,
        $teamSync
    ) {
        $syncError = $teamSync->syncIfStale(TEAM_SYNC_MAX_AGE);
        $html = $view->render('games/new', [
            'title' => 'New Game',
            'sports' => $sports->all(),
            'divisions' => $divisions->all(),
            'schools' => $schools->all(),
            'syncError' => $syncError,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/games', function (Request $request, Response $response) use (
        $games,
        $schools,
        $programs,
        $gameRosters,
        $sports
    ) {
        $data = (array) $request->getParsedBody();

        $sportId = (int) $data['sport_id'];
        $divisionId = ($data['division_id'] ?? '') !== '' ? (int) $data['division_id'] : null;
        $isNeutral = !empty($data['is_neutral_site']);

        // Teams come only from the team API cache; a missing one is added in
        // the API's admin, not here.
        $homeSchool = $schools->find((int) ($data['home_school_id'] ?? 0));
        $awaySchool = $schools->find((int) ($data['away_school_id'] ?? 0));
        if ($homeSchool === null || $awaySchool === null) {
            return $response->withHeader('Location', '/games/new')->withStatus(302);
        }

        $willamette = $schools->findBySlug(SchoolRepository::WILLAMETTE_SLUG);
        $homeIsWillamette = $willamette !== null && (int) $homeSchool['id'] === (int) $willamette['id'];
        $awayIsWillamette = $willamette !== null && (int) $awaySchool['id'] === (int) $willamette['id'];

        $homeProgram = $programs->findOrCreate((int) $homeSchool['id'], $sportId, $divisionId, $homeIsWillamette);
        $awayProgram = $programs->findOrCreate((int) $awaySchool['id'], $sportId, $divisionId, $awayIsWillamette);

        $gameId = $games->create([
            'sport_id' => $sportId,
            'date' => $data['date'],
            'time_of_day' => ($data['time_of_day'] ?? '') !== '' ? $data['time_of_day'] : null,
            'home_program_id' => $homeProgram['id'],
            'away_program_id' => $awayProgram['id'],
            'is_neutral_site' => PgType::boolToString($isNeutral),
            'neutral_site_note' => ($data['neutral_site_note'] ?? '') !== '' ? $data['neutral_site_note'] : null,
            'venue' => ($data['venue'] ?? '') !== '' ? $data['venue'] : null,
            'script_template_id' => null,
            'anthem_note' => ($data['anthem_note'] ?? '') !== '' ? $data['anthem_note'] : null,
            'notes' => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            'status' => 'draft',
        ]);

        $gameRosters->createSnapshot($gameId, (int) $homeProgram['id'], 'home');
        $gameRosters->createSnapshot($gameId, (int) $awayProgram['id'], 'away');

        return $response->withHeader('Location', '/games/' . $gameId)->withStatus(302);
    });

    $app->post('/games/{id}', function (Request $request, Response $response, array $args) use ($games) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $games->delete((int) $args['id']);
        }

        return $response->withHeader('Location', '/games')->withStatus(302);
    });

    $app->get('/games/{id}', function (Request $request, Response $response, array $args) use ($view, $games, $gameRosters) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        $html = $view->render('games/show', [
            'title' => 'Game — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'rosterCounts' => $gameRosters->countsForGame((int) $game['id']),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/games/{id}/roster/{side}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $gameRosters, $programs, $rosterImportUrl) {
        $game = $games->find((int) $args['id']);
        $side = $args['side'];
        if ($game === null || !in_array($side, ['home', 'away'], true)) {
            throw new HttpNotFoundException($request);
        }

        $snapshot = $gameRosters->findSnapshot((int) $game['id'], $side);
        $program = $snapshot !== null && $snapshot['program_id'] !== null
            ? $programs->find((int) $snapshot['program_id'])
            : null;

        $html = $view->render('games/roster', [
            'title' => ($game[$side . '_school_name'] ?? ucfirst($side)) . ' Roster',
            'game' => $game,
            'side' => $side,
            'snapshot' => $snapshot,
            'players' => $snapshot !== null ? $gameRosters->playersFor((int) $snapshot['id']) : [],
            'coaches' => $snapshot !== null ? $gameRosters->coachesFor((int) $snapshot['id']) : [],
            'program' => $program,
            'rosterImport' => $program !== null ? $rosterImportUrl($program, (string) $game['date']) : null,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/games/{id}/roster/{side}/players', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters) {
        $data = (array) $request->getParsedBody();
        $gameRosters->createPlayer((int) $data['snapshot_id'], playerFormData($data));

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/games/{id}/roster/{side}/players/{playerId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $gameRosters->deletePlayer((int) $args['playerId']);
        } else {
            $gameRosters->updatePlayer((int) $args['playerId'], playerFormData($data));
        }

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/games/{id}/roster/{side}/coaches', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters) {
        $data = (array) $request->getParsedBody();
        $gameRosters->createCoach((int) $data['snapshot_id'], $data['coach'], $data['pronunciation'] ?? null, $data['staff_role'] ?? null, (int) ($data['sort_order'] ?? 0));

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/games/{id}/roster/{side}/coaches/{coachId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters) {
        $gameRosters->deleteCoach((int) $args['coachId']);

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/games/{id}/officials', function (
        Request $request,
        Response $response,
        array $args
    ) use ($officials) {
        $data = (array) $request->getParsedBody();
        $officials->create((int) $args['id'], $data['official'], $data['role'] ?? null, (int) ($data['sort_order'] ?? 0));

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    // Shared by /script and /teleprompter — both just render the same
    // resolved template/context differently.
    $resolveScript = function (array $game) use ($scriptTemplates, $contextBuilder, $renderer): array {
        $template = $scriptTemplates->findForGame(
            (int) $game['sport_id'],
            $game['division_id'] !== null ? (int) $game['division_id'] : null,
            (bool) $game['is_neutral_site']
        );

        if ($template === null) {
            return [null, 'No script template found for this sport/division/site combination yet.'];
        }

        $blocks = json_decode($template['content_blocks'], true);
        $context = $contextBuilder->build($game);

        return [$renderer->render($blocks, $context), null];
    };

    $app->get('/games/{id}/script', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $resolveScript) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        [$renderedScript, $missingTemplateReason] = $resolveScript($game);

        $html = $view->render('games/script', [
            'title' => 'Script — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'renderedScript' => $renderedScript,
            'missingTemplateReason' => $missingTemplateReason,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/games/{id}/teleprompter', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $resolveScript) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        [$renderedScript, $missingTemplateReason] = $resolveScript($game);

        $html = $view->render('games/teleprompter', [
            'title' => 'Teleprompter — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'bodyClass' => 'teleprompter-page',
            'game' => $game,
            'renderedScript' => $renderedScript,
            'missingTemplateReason' => $missingTemplateReason,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    // ---- Roster URL fetch ------------------------------------------------
    //
    // The roster page's import panel: the server fetches the school's
    // roster page (browsers can't, cross-origin), the page parses it with
    // the same scraper-core.js the bookmarklet uses, then posts the result
    // to /import/stage so it lands in import_batches for review like any
    // other scrape. When a site blocks the server (WAF bot check), the
    // panel falls back to the bookmarklet or pasted page HTML.

    $app->post('/programs/{programId}/roster-url', function (
        Request $request,
        Response $response,
        array $args
    ) use ($programs) {
        $data = (array) $request->getParsedBody();
        $url = trim((string) ($data['roster_url'] ?? ''));
        $programs->updateRosterUrl((int) $args['programId'], $url !== '' ? $url : null);

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/roster/fetch', function (Request $request, Response $response) use ($programs, $rosterFetcher) {
        $response = $response->withHeader('Content-Type', 'application/json');
        $body = json_decode((string) $request->getBody(), true);
        $url = trim((string) ($body['url'] ?? ''));
        $programId = (int) ($body['programId'] ?? 0);

        // Remember the URL even if the fetch fails, so the bookmarklet/paste
        // fallbacks (and next game) start from it.
        if ($programId > 0 && $url !== '' && $programs->find($programId) !== null) {
            $programs->updateRosterUrl($programId, $url);
        }

        try {
            $page = $rosterFetcher->fetch($url);
            $result = ['ok' => true, 'html' => $page['html'], 'url' => $page['url']];
        } catch (RosterFetchException $e) {
            $result = ['ok' => false, 'error' => $e->getMessage(), 'blocked' => $e instanceof RosterFetchBlocked];
        }

        $response->getBody()->write((string) json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE));

        return $response;
    });

    // In-app counterpart of the token-gated /api/import/stage: a game's
    // roster page posts a scrape here and lands on its review page with
    // that game and side already selected.
    $app->post('/import/stage', function (Request $request, Response $response) use ($importBatches) {
        $data = (array) $request->getParsedBody();
        $scrape = json_decode((string) ($data['scrape'] ?? ''), true);
        if (!is_array($scrape)) {
            return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
        }

        $sourceUrl = is_string($scrape['sourceUrl'] ?? null) && $scrape['sourceUrl'] !== '' ? $scrape['sourceUrl'] : null;
        $batchId = $importBatches->create($sourceUrl, [
            'scrapedVia' => is_string($scrape['scrapedVia'] ?? null) ? $scrape['scrapedVia'] : 'unknown',
            'players' => is_array($scrape['players'] ?? null) ? $scrape['players'] : [],
            'coaches' => is_array($scrape['coaches'] ?? null) ? $scrape['coaches'] : [],
            'rawText' => is_string($scrape['rawText'] ?? null) ? $scrape['rawText'] : '',
        ]);

        $preset = array_filter([
            'game_id' => (int) ($data['game_id'] ?? 0) ?: null,
            'side' => in_array($data['side'] ?? '', ['home', 'away'], true) ? $data['side'] : null,
        ]);

        return $response
            ->withHeader('Location', '/import/review/' . $batchId . ($preset !== [] ? '?' . http_build_query($preset) : ''))
            ->withStatus(302);
    });

    // ---- Bookmarklet / roster import -----------------------------------
    //
    // The bookmarklet itself is a thin loader: it embeds only the import
    // token and injects a <script src="/bookmarklet/scraper.js?t=...">.
    // The actual scraping logic lives here server-side, so it can be fixed
    // (e.g. after a school's roster page markup changes) without asking
    // announcers to reinstall anything. Scrapes always land in
    // import_batches first — nothing touches players/coaches or
    // game_roster_* until a human reviews and explicitly commits.

    $app->get('/bookmarklet', function (Request $request, Response $response) use ($view) {
        $token = $_ENV['IMPORT_TOKEN'] ?? '';
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getAuthority();

        $bookmarkletHref = "javascript:(function(){var s=document.createElement('script');"
            . "s.src=" . json_encode($baseUrl . '/bookmarklet/scraper.js?t=' . urlencode($token) . '&_=', JSON_UNESCAPED_SLASHES)
            . "+Date.now();document.body.appendChild(s);})();";

        $html = $view->render('bookmarklet', [
            'title' => 'Bookmarklet',
            'bookmarkletHref' => $bookmarkletHref,
            'tokenConfigured' => $token !== '',
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/bookmarklet/scraper.js', function (Request $request, Response $response) {
        $response = $response
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Cache-Control', 'no-store');

        $expected = $_ENV['IMPORT_TOKEN'] ?? '';
        $provided = $request->getQueryParams()['t'] ?? '';

        if ($expected === '' || !hash_equals($expected, (string) $provided)) {
            $response->getBody()->write(
                "alert('PA Script bookmarklet token invalid or not configured — reinstall from the Bookmarklet page.');"
            );

            return $response;
        }

        $uri = $request->getUri();
        $stageUrl = $uri->getScheme() . '://' . $uri->getAuthority() . '/api/import/stage';

        $core = file_get_contents(__DIR__ . '/../../public/js/scraper-core.js');

        $network = <<<'JS'
        (function () {
            var result = PaScriptScrape.run();

            // Legacy Presto lists coaches on a separate page; the
            // bookmarklet runs on the school's site, so fetch it same-origin.
            var coachesUrl = result.coaches.length ? null : PaScriptScrape.coachesPageUrl(document, location.href);
            var coaches = !coachesUrl ? Promise.resolve(result.coaches) : fetch(coachesUrl)
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    return PaScriptScrape.coachesFromPage(new DOMParser().parseFromString(html, 'text/html'));
                })
                .catch(function () { return []; });

            coaches.then(function (coaches) {
                return fetch(PA_SCRIPT_STAGE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: PA_SCRIPT_IMPORT_TOKEN,
                        sourceUrl: result.sourceUrl,
                        scrapedVia: result.scrapedVia,
                        players: result.players,
                        coaches: coaches,
                        rawText: result.rawText
                    })
                });
            }).then(function (r) { return r.json(); })
              .then(function (r) {
                  if (r.ok) {
                      window.open(r.reviewUrl, '_blank');
                  } else {
                      alert('PA Script import failed: ' + (r.error || 'unknown error'));
                  }
              })
              .catch(function () {
                  alert('Could not reach the PA Script server.');
              });
        })();
        JS;

        $js = 'var PA_SCRIPT_STAGE_URL = ' . json_encode($stageUrl, JSON_UNESCAPED_SLASHES) . ';'
            . "\n" . 'var PA_SCRIPT_IMPORT_TOKEN = ' . json_encode($provided) . ';'
            . "\n" . $core . "\n" . $network;

        $response->getBody()->write($js);

        return $response;
    });

    $app->options('/api/import/stage', function (Request $request, Response $response) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
    });

    $app->post('/api/import/stage', function (
        Request $request,
        Response $response
    ) use ($importBatches) {
        $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*');

        $body = (array) $request->getParsedBody();
        $expected = $_ENV['IMPORT_TOKEN'] ?? '';
        $provided = $body['token'] ?? '';

        if ($expected === '' || !hash_equals($expected, (string) $provided)) {
            $response->getBody()->write(json_encode(['ok' => false, 'error' => 'Invalid import token']));

            return $response->withStatus(403);
        }

        $players = is_array($body['players'] ?? null) ? $body['players'] : [];
        $coachRows = is_array($body['coaches'] ?? null) ? $body['coaches'] : [];
        $rawText = is_string($body['rawText'] ?? null) ? $body['rawText'] : '';
        $sourceUrl = is_string($body['sourceUrl'] ?? null) ? $body['sourceUrl'] : null;

        if ($players === [] && $coachRows === [] && trim($rawText) === '') {
            $response->getBody()->write(json_encode(['ok' => false, 'error' => 'No roster data found on that page']));

            return $response->withStatus(400);
        }

        $batchId = $importBatches->create($sourceUrl, [
            'scrapedVia' => is_string($body['scrapedVia'] ?? null) ? $body['scrapedVia'] : 'unknown',
            'players' => $players,
            'coaches' => $coachRows,
            'rawText' => $rawText,
        ]);

        $uri = $request->getUri();
        $reviewUrl = $uri->getScheme() . '://' . $uri->getAuthority() . '/import/review/' . $batchId;

        $response->getBody()->write(json_encode(['ok' => true, 'batchId' => $batchId, 'reviewUrl' => $reviewUrl]));

        return $response;
    });

    $app->get('/import/review/{batchId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $importBatches, $games) {
        $batch = $importBatches->find((int) $args['batchId']);
        if ($batch === null) {
            throw new HttpNotFoundException($request);
        }

        $payload = json_decode($batch['raw_payload'], true) ?? [];

        $html = $view->render('import/review', [
            'title' => 'Review Import #' . $batch['id'],
            'batch' => $batch,
            'players' => $payload['players'] ?? [],
            'coaches' => $payload['coaches'] ?? [],
            'rawText' => $payload['rawText'] ?? '',
            'scrapedVia' => $payload['scrapedVia'] ?? 'unknown',
            'games' => $games->upcoming(),
            'preset' => $request->getQueryParams(),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/import/review/{batchId}/commit', function (
        Request $request,
        Response $response,
        array $args
    ) use ($importBatches, $gameRosters) {
        $batch = $importBatches->find((int) $args['batchId']);
        if ($batch === null) {
            throw new HttpNotFoundException($request);
        }

        $data = (array) $request->getParsedBody();
        $gameId = (int) ($data['game_id'] ?? 0);
        $side = in_array($data['side'] ?? '', ['home', 'away'], true) ? $data['side'] : null;
        $snapshot = $gameId > 0 && $side !== null ? $gameRosters->findSnapshot($gameId, $side) : null;
        if ($snapshot === null) {
            return $response->withHeader('Location', '/import/review/' . (int) $batch['id'])->withStatus(302);
        }
        $snapshotId = (int) $snapshot['id'];

        foreach (is_array($data['players'] ?? null) ? $data['players'] : [] as $row) {
            $row = playerFormData($row);
            if (empty($row['include']) || trim($row['name']) === '') {
                continue;
            }
            $gameRosters->createPlayer($snapshotId, $row);
        }

        // Coaches already on this side's roster (matched by name) are
        // skipped, so re-importing the same page doesn't double the staff.
        $haveCoach = [];
        foreach ($gameRosters->coachesFor($snapshotId) as $coach) {
            $haveCoach[strtolower(trim($coach['name']))] = true;
        }
        foreach (is_array($data['coaches'] ?? null) ? $data['coaches'] : [] as $row) {
            $name = trim((string) ($row['coach'] ?? ''));
            $key = strtolower($name);
            if (empty($row['include']) || $name === '' || isset($haveCoach[$key])) {
                continue;
            }
            $haveCoach[$key] = true;
            $gameRosters->createCoach(
                $snapshotId,
                $name,
                trim((string) ($row['pronunciation'] ?? '')),
                trim((string) ($row['staff_role'] ?? '')),
                (int) ($row['sort_order'] ?? 0)
            );
        }

        $importBatches->updateStatus((int) $batch['id'], 'reviewed');

        return $response->withHeader('Location', '/games/' . $gameId . '/roster/' . $side)->withStatus(302);
    });

    $app->post('/import/review/{batchId}/discard', function (
        Request $request,
        Response $response,
        array $args
    ) use ($importBatches) {
        $batch = $importBatches->find((int) $args['batchId']);
        if ($batch === null) {
            throw new HttpNotFoundException($request);
        }

        $importBatches->updateStatus((int) $batch['id'], 'discarded');

        return $response->withHeader('Location', '/bookmarklet')->withStatus(302);
    });
};
