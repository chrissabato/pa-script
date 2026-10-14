<?php

declare(strict_types=1);

use App\Auth\AdminGate;
use App\Auth\PasswordGate;
use App\Database\PgType;
use App\Fill\DocumentFillException;
use App\Fill\GeminiClient;
use App\Fill\RosterFiller;
use App\Rendering\GameContextBuilder;
use App\Rendering\ScriptChecks;
use App\Rendering\Spoken;
use App\Rendering\TemplateRenderer;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameRosterRepository;
use App\Repository\ImportBatchRepository;
use App\Repository\OfficialRepository;
use App\Repository\ProgramRepository;
use App\Repository\SchoolRepository;
use App\Repository\ScriptSnippetRepository;
use App\Repository\ScriptTemplateRepository;
use App\Repository\SportRepository;
use App\Roster\RosterFetchBlocked;
use App\Roster\RosterFetchException;
use App\Roster\RosterPageFetcher;
use App\Roster\RosterUrlGuesser;
use App\Site;
use App\Teams\TeamApiSync;
use App\Templates\Blocks;
use App\Templates\DefaultTemplates;
use App\Templates\TemplateSyntax;
use App\Templates\TemplateSyntaxError;
use App\View\Renderer;
use App\View\Url;
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

/**
 * Where a row edit goes next: autosaving rows (public/js/autosave.js, sent
 * with X-Requested-With: fetch) just need a 204; a plain form post (no JS,
 * or a Delete) redirects as before.
 */
function rowSaved(Request $request, Response $response, string $location): Response
{
    if ($request->getHeaderLine('X-Requested-With') === 'fetch') {
        return $response->withStatus(204);
    }

    return $response->withHeader('Location', $location)->withStatus(302);
}

return function (App $app, PDO $pdo, Renderer $view, PasswordGate $gate, AdminGate $adminGate): void {
    $sports = new SportRepository($pdo);
    $divisions = new DivisionRepository($pdo);
    $schools = new SchoolRepository($pdo);
    $programs = new ProgramRepository($pdo);
    $games = new GameRepository($pdo);
    $gameRosters = new GameRosterRepository($pdo);
    $officials = new OfficialRepository($pdo);
    $scriptTemplates = new ScriptTemplateRepository($pdo);
    $importBatches = new ImportBatchRepository($pdo);
    $teamSync = new TeamApiSync($pdo, Site::teamsApiUrl());
    $contextBuilder = new GameContextBuilder($gameRosters, $officials);
    $snippets = new ScriptSnippetRepository($pdo);
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

    // ---- Login (one shared password; see App\Auth\PasswordGate) ----------

    $app->get('/login', function (Request $request, Response $response) use ($view, $gate) {
        $next = PasswordGate::safeNext((string) ($request->getQueryParams()['next'] ?? '/'));
        if (!$gate->enabled() || $gate->isLoggedIn($request)) {
            return $response->withHeader('Location', Url::to($next))->withStatus(302);
        }
        $response->getBody()->write($view->render('login', [
            'title' => 'Log in — PA Script',
            'next' => $next,
            'failed' => false,
            'hideNav' => true,
        ]));

        return $response;
    });

    $app->post('/login', function (Request $request, Response $response) use ($view, $gate) {
        $data = (array) $request->getParsedBody();
        $next = PasswordGate::safeNext((string) ($data['next'] ?? '/'));
        if ($gate->check((string) ($data['password'] ?? ''))) {
            return $gate->logIn($request, $response)->withHeader('Location', Url::to($next))->withStatus(302);
        }
        sleep(1); // slow down password guessing
        $response->getBody()->write($view->render('login', [
            'title' => 'Log in — PA Script',
            'next' => $next,
            'failed' => true,
            'hideNav' => true,
        ]));

        return $response->withStatus(401);
    });

    $app->post('/logout', function (Request $request, Response $response) use ($gate) {
        return $gate->logOut($request, $response)->withHeader('Location', Url::to('/login'))->withStatus(302);
    });

    // ---- Admin unlock (template pages; see App\Auth\AdminGate) ------------

    $app->get('/admin/login', function (Request $request, Response $response) use ($view, $adminGate) {
        $next = PasswordGate::safeNext((string) ($request->getQueryParams()['next'] ?? '/templates'));
        if ($adminGate->isUnlocked($request)) {
            return $response->withHeader('Location', Url::to($next))->withStatus(302);
        }
        $response->getBody()->write($view->render('admin-login', [
            'title' => 'Admin — PA Script',
            'next' => $next,
            'failed' => false,
        ]));

        return $response;
    });

    $app->post('/admin/login', function (Request $request, Response $response) use ($view, $adminGate) {
        $data = (array) $request->getParsedBody();
        $next = PasswordGate::safeNext((string) ($data['next'] ?? '/templates'));
        if ($adminGate->check((string) ($data['password'] ?? ''))) {
            return $adminGate->unlock($request, $response)->withHeader('Location', Url::to($next))->withStatus(302);
        }
        sleep(1); // slow down password guessing
        $response->getBody()->write($view->render('admin-login', [
            'title' => 'Admin — PA Script',
            'next' => $next,
            'failed' => true,
        ]));

        return $response->withStatus(401);
    });

    $app->post('/admin/logout', function (Request $request, Response $response) use ($adminGate) {
        return $adminGate->lock($request, $response)->withHeader('Location', Url::to('/'))->withStatus(302);
    });

    // ---- Dashboard -------------------------------------------------------

    $app->get('/', function (Request $request, Response $response) use ($view, $games) {
        $today = date('Y-m-d');
        $todayGames = [];
        $upcomingGames = [];
        foreach ($games->onOrAfter($today) as $game) {
            if ($game['date'] === $today) {
                $todayGames[] = $game;
            } else {
                $upcomingGames[] = $game;
            }
        }

        $html = $view->render('home', [
            'title' => 'PA Script — Dashboard',
            'todayGames' => $todayGames,
            'upcomingGames' => $upcomingGames,
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

        return $response->withHeader('Location', Url::base() . '/schools?' . http_build_query($params))->withStatus(302);
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
        $schools,
        $teamSync
    ) {
        $syncError = $teamSync->syncIfStale(TEAM_SYNC_MAX_AGE);
        $html = $view->render('games/new', [
            'title' => 'New Game',
            'schools' => $schools->all(),
            'syncError' => $syncError,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/games', function (Request $request, Response $response) use (
        $games,
        $schools,
        $divisions,
        $programs,
        $gameRosters,
        $sports
    ) {
        $data = (array) $request->getParsedBody();

        $backToForm = $response->withHeader('Location', Url::base() . '/games/new')->withStatus(302);

        // One "Team" pick ("soccer/womens") is the game's sport + division.
        $team = SportRepository::parseTeam((string) ($data['team'] ?? ''));
        $sport = $team !== null ? $sports->findBySlug($team[0]) : null;
        $division = $team !== null && $team[1] !== null ? $divisions->findBySlug($team[1]) : null;
        if ($sport === null || ($team[1] !== null && $division === null)) {
            return $backToForm;
        }
        $sportId = (int) $sport['id'];
        $divisionId = $division !== null ? (int) $division['id'] : null;
        $isNeutral = !empty($data['is_neutral_site']);

        // Teams come only from the team API cache; a missing one is added in
        // the API's admin, not here.
        $homeSchool = $schools->find((int) ($data['home_school_id'] ?? 0));
        $awaySchool = $schools->find((int) ($data['away_school_id'] ?? 0));
        if ($homeSchool === null || $awaySchool === null) {
            return $backToForm;
        }

        // Men's/women's entries of one school: use the one for this division.
        $homeSchool = $schools->forDivision($homeSchool, $division);
        $awaySchool = $schools->forDivision($awaySchool, $division);

        $ours = $schools->findBySlug(Site::homeTeamSlug());
        $homeIsOurs = $ours !== null && (int) $homeSchool['id'] === (int) $ours['id'];
        $awayIsOurs = $ours !== null && (int) $awaySchool['id'] === (int) $ours['id'];

        $homeProgram = $programs->findOrCreate((int) $homeSchool['id'], $sportId, $divisionId, $homeIsOurs);
        $awayProgram = $programs->findOrCreate((int) $awaySchool['id'], $sportId, $divisionId, $awayIsOurs);

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
            'is_conference' => PgType::boolToString(!empty($data['is_conference'])),
        ]);

        // Each side starts from that team's latest roster this academic year,
        // if it already played one; otherwise it starts empty.
        foreach (['home' => $homeProgram, 'away' => $awayProgram] as $side => $program) {
            $snapshotId = $gameRosters->createSnapshot($gameId, (int) $program['id'], $side);
            $gameRosters->copyFromLatestThisYear($snapshotId, (int) $program['id'], $gameId, (string) $data['date']);
        }

        return $response->withHeader('Location', Url::base() . '/games/' . $gameId)->withStatus(302);
    });

    $app->post('/games/{id}', function (Request $request, Response $response, array $args) use ($games) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $games->delete((int) $args['id']);
        }

        return $response->withHeader('Location', Url::base() . '/games')->withStatus(302);
    });

    $app->get('/games/{id}', function (Request $request, Response $response, array $args) use (
        $view,
        $games,
        $gameRosters,
        $officials
    ) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        // The venue the script reads when the field is blank, for its hint
        // (a neutral site has no usual venue).
        $defaultVenue = !$game['is_neutral_site'] && trim((string) $game['sport_default_venue']) !== ''
            ? (string) $game['sport_default_venue']
            : null;

        $html = $view->render('games/show', [
            'title' => 'Game — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'rosterCounts' => $gameRosters->countsForGame((int) $game['id']),
            'officials' => $officials->findByGame((int) $game['id']),
            'defaultVenue' => $defaultVenue,
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
            'fillEnabled' => ($_ENV['GEMINI_API_KEY'] ?? '') !== '',
            'filled' => isset($request->getQueryParams()['filled']) ? (int) $request->getQueryParams()['filled'] : null,
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

        return rowSaved($request, $response, $request->getHeaderLine('Referer'));
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
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $gameRosters->deleteCoach((int) $args['coachId']);
        } else {
            $seasons = trim((string) ($data['seasons'] ?? ''));
            $gameRosters->updateCoach(
                (int) $args['coachId'],
                trim((string) ($data['coach'] ?? '')),
                trim((string) ($data['pronunciation'] ?? '')),
                trim((string) ($data['staff_role'] ?? '')),
                trim((string) ($data['pronoun'] ?? '')),
                ctype_digit($seasons) ? (int) $seasons : null,
                (int) ($data['sort_order'] ?? 0)
            );
        }

        return rowSaved($request, $response, $request->getHeaderLine('Referer'));
    });

    // ---- Fill in from a document (Gemini; see App\Fill\RosterFiller) -------
    // Upload → Gemini suggests values for blank fields → a review page →
    // only the ticked suggestions are written, and never over a value.

    $rosterSide = function (Request $request, array $args) use ($games, $gameRosters): array {
        $game = $games->find((int) $args['id']);
        $side = $args['side'];
        $snapshot = $game !== null && in_array($side, ['home', 'away'], true)
            ? $gameRosters->findSnapshot((int) $game['id'], $side)
            : null;
        if ($snapshot === null) {
            throw new HttpNotFoundException($request);
        }

        return [$game, $side, $snapshot];
    };

    $app->post('/games/{id}/roster/{side}/fill', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $gameRosters, $rosterSide) {
        list($game, $side, $snapshot) = $rosterSide($request, $args);
        $players = $gameRosters->playersFor((int) $snapshot['id']);
        $coaches = $gameRosters->coachesFor((int) $snapshot['id']);
        $suggestions = null;
        $error = null;
        $filename = '';

        try {
            $apiKey = (string) ($_ENV['GEMINI_API_KEY'] ?? '');
            if ($apiKey === '') {
                throw new DocumentFillException('Document fill isn\'t set up: GEMINI_API_KEY is missing from the server\'s .env.');
            }
            $file = $request->getUploadedFiles()['document'] ?? null;
            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                throw new DocumentFillException('Choose a PDF or Word document to upload.');
            }
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                || $file->getSize() > RosterFiller::MAX_BYTES) {
                throw new DocumentFillException('That file is too large to upload (the server allows '
                    . ini_get('upload_max_filesize') . '; Gemini up to ' . (RosterFiller::MAX_BYTES >> 20) . ' MB).');
            }
            if ($file->getError() !== UPLOAD_ERR_OK) {
                throw new DocumentFillException('The upload failed (error ' . $file->getError() . '). Try again.');
            }
            $filename = (string) $file->getClientFilename();

            set_time_limit(180); // CPU time only; GeminiClient caps the wall-clock wait
            $filler = new RosterFiller(GeminiClient::fromEnv($_ENV));
            $suggestions = $filler->suggest(
                $filename,
                (string) $file->getStream(),
                (string) ($game[$side . '_school_name'] ?? ucfirst($side) . ' team'),
                (string) ($game['sport_slug'] ?? ''),
                $players,
                $coaches
            );
        } catch (DocumentFillException $e) {
            $error = $e->getMessage();
        }

        $response->getBody()->write($view->render('games/fill-review', [
            'title' => 'Check against ' . ($filename !== '' ? $filename : 'a document'),
            'game' => $game,
            'side' => $side,
            'filename' => $filename,
            'suggestions' => $suggestions,
            'error' => $error,
        ]));

        return $response;
    });

    $app->post('/games/{id}/roster/{side}/fill/apply', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters, $rosterSide) {
        list($game, $side, $snapshot) = $rosterSide($request, $args);
        $data = (array) $request->getParsedBody();
        $values = (array) ($data['value'] ?? []);
        $currents = (array) ($data['current'] ?? []);
        $filled = 0;
        foreach ((array) ($data['apply'] ?? []) as $key) {
            // key = "player:12:pronunciation" / "coach:3:seasons"
            $parts = explode(':', (string) $key);
            if (count($parts) !== 3 || !isset($values[$key])) {
                continue;
            }
            list($kind, $id, $field) = $parts;
            $fields = $kind === 'player' ? RosterFiller::PLAYER_FIELDS : RosterFiller::COACH_FIELDS;
            $value = in_array($field, $fields, true) ? RosterFiller::clean($field, (string) $values[$key]) : null;
            if ($value === null || !in_array($kind, ['player', 'coach'], true)) {
                continue;
            }
            // The roster's value when the review page was shown; the save is skipped if it has changed since.
            $current = isset($currents[$key]) ? (string) $currents[$key] : null;
            $changed = $kind === 'player'
                ? $gameRosters->fillPlayerField((int) $snapshot['id'], (int) $id, $field, (string) $value, $current)
                : $gameRosters->fillCoachField((int) $snapshot['id'], (int) $id, $field, $value, $current);
            $filled += $changed ? 1 : 0;
        }

        return $response
            ->withHeader('Location', Url::to('/games/' . (int) $game['id'] . '/roster/' . $side . '?filled=' . $filled))
            ->withStatus(302);
    });

    // Draft / Ready, from the game header on any game tab; returns to that tab.
    $app->post('/games/{id}/status', function (Request $request, Response $response, array $args) use ($games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }
        $status = (string) (((array) $request->getParsedBody())['status'] ?? '');
        if (isset(GameRepository::STATUSES[$status])) {
            $games->updateStatus((int) $game['id'], $status);
        }
        $back = $request->getHeaderLine('Referer');

        return $response->withHeader('Location', $back !== '' ? $back : Url::to('/games/' . (int) $game['id']))->withStatus(302);
    });

    // Script/teleprompter name display (Name / Pronunciation / both);
    // returns to whichever of the two pages it was changed on.
    $app->post('/games/{id}/name-display', function (
        Request $request,
        Response $response,
        array $args
    ) use ($games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }
        $data = (array) $request->getParsedBody();
        $display = (string) ($data['name_display'] ?? '');
        if (isset(Spoken::NAME_DISPLAYS[$display])) {
            $games->updateNameDisplay((int) $game['id'], $display);
        }
        $page = ($data['return_to'] ?? '') === 'teleprompter' ? 'teleprompter' : 'script';

        return $response->withHeader('Location', Url::base() . '/games/' . (int) $game['id'] . '/' . $page)->withStatus(302);
    });

    $app->post('/games/{id}/details', function (
        Request $request,
        Response $response,
        array $args
    ) use ($games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }
        $data = (array) $request->getParsedBody();
        $announcement = trim((string) ($data['special_announcement'] ?? ''));
        $closing = trim((string) ($data['closing_announcement'] ?? ''));
        $games->updateDetails((int) $game['id'], [
            'time_of_day' => ($data['time_of_day'] ?? '') !== '' ? $data['time_of_day'] : null,
            'venue' => trim((string) ($data['venue'] ?? '')) !== '' ? trim((string) $data['venue']) : null,
            'is_conference' => PgType::boolToString(!empty($data['is_conference'])),
            'has_anthem' => PgType::boolToString(empty($data['no_anthem'])),
            'special_announcement' => $announcement !== '' ? $announcement : null,
            'closing_announcement' => $closing !== '' ? $closing : null,
        ]);

        return $response->withHeader('Location', Url::base() . '/games/' . (int) $game['id'])->withStatus(302);
    });

    $app->post('/games/{id}/officials', function (
        Request $request,
        Response $response,
        array $args
    ) use ($officials) {
        $data = (array) $request->getParsedBody();
        $officials->create((int) $args['id'], trim((string) $data['official']), trim((string) ($data['role'] ?? '')), (int) ($data['sort_order'] ?? 0));

        return $response->withHeader('Location', Url::base() . '/games/' . (int) $args['id'] . '#officials')->withStatus(302);
    });

    $app->post('/games/{id}/officials/{officialId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($officials) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $officials->delete((int) $args['officialId'], (int) $args['id']);
        } else {
            $officials->update(
                (int) $args['officialId'],
                (int) $args['id'],
                trim((string) ($data['official'] ?? '')),
                trim((string) ($data['role'] ?? '')),
                (int) ($data['sort_order'] ?? 0)
            );
        }

        return rowSaved($request, $response, Url::base() . '/games/' . (int) $args['id'] . '#officials');
    });

    // Printable rosters, teams side by side (like the source sheets' Roster
    // tab). ?starters=1 prints just the starters; ?show_starters=1 marks
    // starters and liberos (hidden by default).
    $app->get('/games/{id}/print', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $contextBuilder) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        $startersOnly = !empty($request->getQueryParams()['starters']);
        $showStarters = !empty($request->getQueryParams()['show_starters']);
        $context = $contextBuilder->build($game);
        $teams = [];
        // Away on the left, home on the right, as on the sheet.
        foreach (['away', 'home'] as $side) {
            $sideContext = $context[$side];
            $players = array_merge($sideContext['starters'], $sideContext['reserves'], $sideContext['battingOrder']);
            // Jersey order, as on the roster page (starters are flagged, not grouped).
            usort($players, function (array $a, array $b): int {
                $na = preg_replace('/\D/', '', (string) $a['number']);
                $nb = preg_replace('/\D/', '', (string) $b['number']);
                if ($na === '' || $nb === '') {
                    return ($na === '') <=> ($nb === '') ?: strcmp((string) $a['name'], (string) $b['name']);
                }

                return (int) $na <=> (int) $nb ?: strcmp((string) $a['name'], (string) $b['name']);
            });
            if ($startersOnly) {
                $players = array_values(array_filter($players, function (array $p): bool {
                    return (bool) $p['is_starter'] || $p['batting_order'] !== null;
                }));
            }
            $teams[$side] = [
                'side' => $side,
                'schoolName' => $sideContext['schoolName'],
                'mascot' => $sideContext['mascot'],
                // From the team API (schools cache): logo URL, and the
                // team-colour background it's designed to sit on.
                'logo' => $game[$side . '_logo'] ?? null,
                'logoBg' => $game[$side . '_logo_bg'] ?? null,
                'headCoach' => $sideContext['headCoach'],
                'assistants' => array_values(array_filter($sideContext['coaches'], function (array $c) use ($sideContext): bool {
                    return $sideContext['headCoach'] === null || (int) $c['id'] !== (int) $sideContext['headCoach']['id'];
                })),
                'players' => $players,
            ];
        }

        $html = $view->render('games/print', [
            'title' => 'Rosters — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'bodyClass' => 'print-page',
            'game' => $game,
            'teams' => $teams,
            'startersOnly' => $startersOnly,
            'showStarters' => $showStarters,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    // Shared by /script and /teleprompter — both just render the same
    // resolved template/context differently.
    $resolveScript = function (array $game) use ($scriptTemplates, $contextBuilder, $snippets): array {
        $template = $scriptTemplates->findForGame(
            (int) $game['sport_id'],
            $game['division_id'] !== null ? (int) $game['division_id'] : null,
            (bool) $game['is_neutral_site']
        );

        if ($template === null) {
            return [null, 'No script template found for this sport/division/site combination yet.', []];
        }

        $blocks = json_decode($template['content_blocks'], true);
        $context = $contextBuilder->build($game);

        $renderer = new TemplateRenderer($snippets->blocksByName());

        return [$renderer->render($blocks, $context), null, ScriptChecks::forGame($game, $context)];
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

        [$renderedScript, $missingTemplateReason, $scriptChecks] = $resolveScript($game);
        $isCustomized = $game['custom_script'] !== null;

        $html = $view->render('games/script', [
            'bodyClass' => 'script-page',
            'scriptChecks' => $scriptChecks,
            'title' => 'Script — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'renderedScript' => $isCustomized ? $game['custom_script'] : $renderedScript,
            'missingTemplateReason' => $isCustomized ? null : $missingTemplateReason,
            'isCustomized' => $isCustomized,
            'autoPrint' => ($request->getQueryParams()['print'] ?? '') === '1',
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
        $isCustomized = $game['custom_script'] !== null;

        $html = $view->render('games/teleprompter', [
            'title' => 'Teleprompter — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'bodyClass' => 'teleprompter-page',
            'game' => $game,
            'renderedScript' => $isCustomized ? $game['custom_script'] : $renderedScript,
            'missingTemplateReason' => $isCustomized ? null : $missingTemplateReason,
            'isCustomized' => $isCustomized,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    // Customize: edit a copy of the script (the saved copy if there is one,
    // else the generated script). Once saved it replaces the generated
    // script on the Script tab and teleprompter until reset.
    $app->get('/games/{id}/script/customize', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $resolveScript) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        [$renderedScript] = $resolveScript($game);

        $html = $view->render('games/customize-script', [
            'title' => 'Customize script — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'scriptText' => $game['custom_script'] !== null ? $game['custom_script'] : (string) $renderedScript,
            'isCustomized' => $game['custom_script'] !== null,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/games/{id}/script/customize', function (
        Request $request,
        Response $response,
        array $args
    ) use ($games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }
        $data = (array) $request->getParsedBody();
        // Browsers submit textarea line breaks as CRLF.
        $text = str_replace("\r\n", "\n", (string) ($data['custom_script'] ?? ''));
        $games->updateCustomScript((int) $game['id'], trim($text) !== '' ? $text : null);

        return $response->withHeader('Location', Url::base() . '/games/' . (int) $game['id'] . '/script')->withStatus(302);
    });

    $app->post('/games/{id}/script/reset', function (
        Request $request,
        Response $response,
        array $args
    ) use ($games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }
        $games->updateCustomScript((int) $game['id'], null);

        return $response->withHeader('Location', Url::base() . '/games/' . (int) $game['id'] . '/script')->withStatus(302);
    });

    // ---- Script templates and snippets ------------------------------------
    //
    // The template editor: a template's (or global snippet's) blocks as
    // TemplateSyntax text, with a live preview against a game. Saving marks
    // it edited, so ScriptTemplatesSeeder stops resetting it; Reset puts
    // DefaultTemplates' wording back. The list page also holds each sport's
    // default venue.

    $app->get('/templates', function (Request $request, Response $response) use ($view, $scriptTemplates, $snippets, $sports) {
        $html = $view->render('templates/index', [
            'title' => 'Script templates',
            'templates' => $scriptTemplates->allWithNames(),
            'snippets' => $snippets->all(),
            'defaultSnippets' => array_keys((new DefaultTemplates())->snippets()),
            'sports' => $sports->all(),
            'snippetError' => $request->getQueryParams()['snippet_error'] ?? null,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/sports/{id}/default-venue', function (Request $request, Response $response, array $args) use ($sports) {
        $venue = trim((string) (((array) $request->getParsedBody())['default_venue'] ?? ''));
        $sports->updateDefaultVenue((int) $args['id'], $venue !== '' ? $venue : null);

        return rowSaved($request, $response, Url::base() . '/templates');
    });

    // The editor page for a template or a snippet; $item describes which.
    $renderEditor = function (Request $request, Response $response, array $item, string $text, ?TemplateSyntaxError $error = null) use ($view, $snippets) {
        $query = $request->getQueryParams();
        $html = $view->render('templates/edit', array_merge($item, [
            'title' => 'Edit — ' . $item['heading'],
            'bodyClass' => 'template-editor-page',
            'text' => $text,
            'error' => $error,
            'saved' => isset($query['saved']),
            'wasReset' => isset($query['reset']),
            // A snippet can use other snippets, not itself.
            'snippetNames' => array_values(array_diff($snippets->names(), [$item['snippetName'] ?? null])),
        ]));
        $response->getBody()->write($html);

        return $response;
    };

    // The games a template's preview can use: the sport's games that would
    // read this template (same division when it has one, same site).
    $templateItem = function (array $template) use ($games): array {
        $previewGames = array_values(array_filter($games->forSport((int) $template['sport_id']), function (array $game) use ($template): bool {
            return ($template['division_id'] === null || (int) $game['division_id'] === (int) $template['division_id'])
                && (bool) $game['is_neutral_site'] === ($template['site_variant'] === 'neutral');
        }));

        return [
            'kind' => 'template',
            'heading' => $template['name'],
            'editorUrl' => Url::to('/templates/' . (int) $template['id']),
            'editedAt' => $template['edited_at'],
            'previewGames' => $previewGames,
            'hasDefault' => (new DefaultTemplates())->blocksFor($template['sport_slug'], $template['division_slug'], $template['site_variant']) !== null,
        ];
    };

    // A snippet previews against any recent game; it's shared by every sport.
    $snippetItem = function (array $snippet) use ($games, $scriptTemplates, $snippets): array {
        $name = (string) $snippet['name'];
        $usedBy = [];
        foreach ($scriptTemplates->allWithNames() as $template) {
            if (Blocks::usesSnippet((array) json_decode($template['content_blocks'], true), $name)) {
                $usedBy[] = $template['name'];
            }
        }
        foreach ($snippets->all() as $other) {
            if ($other['name'] !== $name && Blocks::usesSnippet((array) json_decode($other['content_blocks'], true), $name)) {
                $usedBy[] = 'Snippet: ' . $other['name'];
            }
        }

        return [
            'kind' => 'snippet',
            'heading' => 'Snippet: ' . $name,
            'snippetName' => $name,
            'editorUrl' => Url::to('/snippets/' . (int) $snippet['id']),
            'editedAt' => $snippet['edited_at'],
            'previewGames' => array_slice(array_reverse($games->upcoming()), 0, 40),
            'hasDefault' => array_key_exists($name, (new DefaultTemplates())->snippets()),
            'usedBy' => $usedBy,
        ];
    };

    // Renders unsaved editor text against a game (or a blank one) as JSON.
    $previewResponse = function (Request $request, Response $response, string $sportSlug, ?string $snippetName = null) use (
        $games,
        $contextBuilder,
        $snippets
    ) {
        $data = (array) $request->getParsedBody();
        try {
            // A snippet can't use itself, as when saving.
            $blocks = TemplateSyntax::parse((string) ($data['text'] ?? ''), array_values(array_diff($snippets->names(), [$snippetName])));
            $game = !empty($data['game_id']) ? $games->find((int) $data['game_id']) : null;
            // No game picked (or none yet): an empty one, so the preview
            // still shows the wording with blank names.
            $context = $game !== null
                ? $contextBuilder->build($game)
                : GameContextBuilder::assemble(
                    ['sport_slug' => $sportSlug],
                    ['home' => ['players' => [], 'coaches' => []], 'away' => ['players' => [], 'coaches' => []]],
                    []
                );
            $result = ['script' => (new TemplateRenderer($snippets->blocksByName()))->render($blocks, $context)];
        } catch (TemplateSyntaxError $e) {
            $result = ['error' => $e->getMessage(), 'line' => $e->getTemplateLine()];
        }
        $response->getBody()->write((string) json_encode($result));

        return $response->withHeader('Content-Type', 'application/json');
    };

    $app->get('/templates/{id}', function (Request $request, Response $response, array $args) use ($scriptTemplates, $renderEditor, $templateItem) {
        $template = $scriptTemplates->findWithNames((int) $args['id']);
        if ($template === null) {
            throw new HttpNotFoundException($request);
        }

        return $renderEditor($request, $response, $templateItem($template), TemplateSyntax::toText((array) json_decode($template['content_blocks'], true)));
    });

    $app->post('/templates/{id}', function (Request $request, Response $response, array $args) use ($scriptTemplates, $snippets, $renderEditor, $templateItem) {
        $template = $scriptTemplates->findWithNames((int) $args['id']);
        if ($template === null) {
            throw new HttpNotFoundException($request);
        }
        $text = (string) (((array) $request->getParsedBody())['text'] ?? '');
        try {
            $blocks = TemplateSyntax::parse($text, $snippets->names());
        } catch (TemplateSyntaxError $e) {
            return $renderEditor($request, $response->withStatus(422), $templateItem($template), $text, $e);
        }
        $scriptTemplates->updateBlocks((int) $template['id'], $blocks, true);

        return $response->withHeader('Location', Url::base() . '/templates/' . (int) $template['id'] . '?saved=1')->withStatus(302);
    });

    $app->post('/templates/{id}/preview', function (Request $request, Response $response, array $args) use ($scriptTemplates, $previewResponse) {
        $template = $scriptTemplates->findWithNames((int) $args['id']);
        if ($template === null) {
            throw new HttpNotFoundException($request);
        }

        return $previewResponse($request, $response, $template['sport_slug']);
    });

    $app->post('/templates/{id}/reset', function (Request $request, Response $response, array $args) use ($scriptTemplates) {
        $template = $scriptTemplates->findWithNames((int) $args['id']);
        if ($template === null) {
            throw new HttpNotFoundException($request);
        }
        $blocks = (new DefaultTemplates())->blocksFor($template['sport_slug'], $template['division_slug'], $template['site_variant']);
        if ($blocks !== null) {
            $scriptTemplates->updateBlocks((int) $template['id'], $blocks, false);
        }

        return $response->withHeader('Location', Url::base() . '/templates/' . (int) $template['id'] . '?reset=1')->withStatus(302);
    });

    $app->post('/snippets', function (Request $request, Response $response) use ($snippets) {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) (((array) $request->getParsedBody())['snippet_name'] ?? '')));
        $error = null;
        if ($name === '' || strlen($name) > 100 || preg_match('/[\[\]{}]/', $name)) {
            $error = 'Give the snippet a name (up to 100 characters, no brackets or braces).';
        } else {
            foreach ($snippets->names() as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    $error = 'There\'s already a snippet called "' . $existing . '".';
                }
            }
        }
        if ($error !== null) {
            return $response->withHeader('Location', Url::base() . '/templates?snippet_error=' . rawurlencode($error))->withStatus(302);
        }

        return $response->withHeader('Location', Url::base() . '/snippets/' . $snippets->create($name))->withStatus(302);
    });

    $app->get('/snippets/{id}', function (Request $request, Response $response, array $args) use ($snippets, $renderEditor, $snippetItem) {
        $snippet = $snippets->find((int) $args['id']);
        if ($snippet === null) {
            throw new HttpNotFoundException($request);
        }

        return $renderEditor($request, $response, $snippetItem($snippet), TemplateSyntax::toText((array) json_decode($snippet['content_blocks'], true)));
    });

    $app->post('/snippets/{id}', function (Request $request, Response $response, array $args) use ($snippets, $renderEditor, $snippetItem) {
        $snippet = $snippets->find((int) $args['id']);
        if ($snippet === null) {
            throw new HttpNotFoundException($request);
        }
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            // Only an unused snippet can go; the button is hidden otherwise.
            if ($snippetItem($snippet)['usedBy'] === []) {
                $snippets->delete((int) $snippet['id']);
            }

            return $response->withHeader('Location', Url::base() . '/templates')->withStatus(302);
        }
        $text = (string) ($data['text'] ?? '');
        try {
            $blocks = TemplateSyntax::parse($text, array_values(array_diff($snippets->names(), [$snippet['name']])));
        } catch (TemplateSyntaxError $e) {
            return $renderEditor($request, $response->withStatus(422), $snippetItem($snippet), $text, $e);
        }
        $snippets->updateBlocks((int) $snippet['id'], $blocks, true);

        return $response->withHeader('Location', Url::base() . '/snippets/' . (int) $snippet['id'] . '?saved=1')->withStatus(302);
    });

    $app->post('/snippets/{id}/preview', function (Request $request, Response $response, array $args) use ($snippets, $previewResponse) {
        $snippet = $snippets->find((int) $args['id']);
        if ($snippet === null) {
            throw new HttpNotFoundException($request);
        }

        return $previewResponse($request, $response, '', $snippet['name']);
    });

    $app->post('/snippets/{id}/reset', function (Request $request, Response $response, array $args) use ($snippets) {
        $snippet = $snippets->find((int) $args['id']);
        if ($snippet === null) {
            throw new HttpNotFoundException($request);
        }
        $defaults = (new DefaultTemplates())->snippets();
        if (isset($defaults[$snippet['name']])) {
            $snippets->updateBlocks((int) $snippet['id'], $defaults[$snippet['name']], false);
        }

        return $response->withHeader('Location', Url::base() . '/snippets/' . (int) $snippet['id'] . '?reset=1')->withStatus(302);
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
            ->withHeader('Location', Url::base() . '/import/review/' . $batchId . ($preset !== [] ? '?' . http_build_query($preset) : ''))
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
        $baseUrl = $uri->getScheme() . '://' . $uri->getAuthority() . Url::base();

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
        $stageUrl = $uri->getScheme() . '://' . $uri->getAuthority() . Url::to('/api/import/stage');

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
        $reviewUrl = $uri->getScheme() . '://' . $uri->getAuthority() . Url::to('/import/review/' . $batchId);

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
            return $response->withHeader('Location', Url::base() . '/import/review/' . (int) $batch['id'])->withStatus(302);
        }
        $snapshotId = (int) $snapshot['id'];
        $replace = !empty($data['replace']);
        $nameKey = function ($name): string {
            return strtolower(trim((string) $name));
        };

        $players = [];
        foreach (is_array($data['players'] ?? null) ? $data['players'] : [] as $row) {
            $row = playerFormData($row);
            if (!empty($row['include']) && trim($row['name']) !== '') {
                $players[] = $row;
            }
        }
        $coaches = [];
        foreach (is_array($data['coaches'] ?? null) ? $data['coaches'] : [] as $row) {
            if (!empty($row['include']) && trim((string) ($row['coach'] ?? '')) !== '') {
                $coaches[] = $row;
            }
        }

        // Replacing (the default) clears the side's players and/or coaches —
        // only the lists this import actually brings — so a fetch onto a
        // roster copied from an earlier game doesn't double it. What a scrape
        // can't know (captain, a hand-typed pronunciation, a coach's pronoun
        // and seasons) carries over from the old row with the same name.
        $oldPlayers = [];
        if ($replace && $players !== []) {
            foreach ($gameRosters->playersFor($snapshotId) as $old) {
                $oldPlayers[$nameKey($old['name'])] = $old;
            }
            $gameRosters->deletePlayersFor($snapshotId);
        }
        $oldCoaches = [];
        if ($replace && $coaches !== []) {
            foreach ($gameRosters->coachesFor($snapshotId) as $old) {
                $oldCoaches[$nameKey($old['name'])] = $old;
            }
            $gameRosters->deleteCoachesFor($snapshotId);
        }

        foreach ($players as $row) {
            $old = $oldPlayers[$nameKey($row['name'])] ?? null;
            if ($old !== null) {
                if (trim((string) ($row['pronunciation'] ?? '')) === '') {
                    $row['pronunciation'] = (string) $old['pronunciation'];
                }
                if ($old['is_captain']) {
                    $row['is_captain'] = '1';
                }
            }
            $gameRosters->createPlayer($snapshotId, $row);
        }

        // Without replacing, coaches already on this side's roster (matched
        // by name) are skipped, so re-importing doesn't double the staff.
        $haveCoach = [];
        foreach ($gameRosters->coachesFor($snapshotId) as $coach) {
            $haveCoach[$nameKey($coach['name'])] = true;
        }
        foreach ($coaches as $row) {
            $name = trim((string) $row['coach']);
            $key = $nameKey($name);
            if (isset($haveCoach[$key])) {
                continue;
            }
            $haveCoach[$key] = true;
            $old = $oldCoaches[$key] ?? null;
            $pronunciation = trim((string) ($row['pronunciation'] ?? ''));
            if ($pronunciation === '' && $old !== null) {
                $pronunciation = (string) $old['pronunciation'];
            }
            $gameRosters->createCoach(
                $snapshotId,
                $name,
                $pronunciation,
                trim((string) ($row['staff_role'] ?? '')),
                (int) ($row['sort_order'] ?? 0),
                $old !== null ? $old['pronoun'] : null,
                $old !== null && $old['seasons'] !== null ? (int) $old['seasons'] : null
            );
        }

        $importBatches->updateStatus((int) $batch['id'], 'reviewed');

        return $response->withHeader('Location', Url::base() . '/games/' . $gameId . '/roster/' . $side)->withStatus(302);
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

        return $response->withHeader('Location', Url::base() . '/bookmarklet')->withStatus(302);
    });
};
