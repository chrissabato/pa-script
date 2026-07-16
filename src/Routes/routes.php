<?php

declare(strict_types=1);

use App\Database\PgType;
use App\Rendering\GameContextBuilder;
use App\Rendering\TemplateRenderer;
use App\Repository\CoachRepository;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameRosterRepository;
use App\Repository\OfficialRepository;
use App\Repository\PlayerRepository;
use App\Repository\ProgramRepository;
use App\Repository\SchoolRepository;
use App\Repository\ScriptTemplateRepository;
use App\Repository\SportRepository;
use App\View\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

return function (App $app, PDO $pdo, Renderer $view): void {
    $sports = new SportRepository($pdo);
    $divisions = new DivisionRepository($pdo);
    $schools = new SchoolRepository($pdo);
    $programs = new ProgramRepository($pdo);
    $players = new PlayerRepository($pdo);
    $coaches = new CoachRepository($pdo);
    $games = new GameRepository($pdo);
    $gameRosters = new GameRosterRepository($pdo);
    $officials = new OfficialRepository($pdo);
    $scriptTemplates = new ScriptTemplateRepository($pdo);
    $contextBuilder = new GameContextBuilder($gameRosters, $officials);
    $renderer = new TemplateRenderer();

    // ---- Sports / roster ----------------------------------------------

    $app->get('/sports', function (Request $request, Response $response) use ($view, $sports, $programs) {
        $html = $view->render('sports/index', [
            'title' => 'Sports',
            'sports' => $sports->all(),
            'willamettePrograms' => $programs->allWillamette(),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/sports/{sport}/{division}/roster', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $sports, $divisions, $programs, $players, $coaches) {
        $sport = $sports->findBySlug($args['sport']);
        $division = $divisions->findBySlug($args['division']);
        if ($sport === null || $division === null) {
            throw new HttpNotFoundException($request);
        }

        $program = $programs->findWillametteProgram((int) $sport['id'], (int) $division['id']);
        if ($program === null) {
            throw new HttpNotFoundException($request);
        }

        $html = $view->render('sports/roster', [
            'title' => $sport['name'] . ' — ' . $division['name'] . ' Roster',
            'sport' => $sport,
            'division' => $division,
            'program' => $program,
            'players' => $players->findByProgram((int) $program['id']),
            'coaches' => $coaches->findByProgram((int) $program['id']),
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->post('/programs/{programId}/players', function (
        Request $request,
        Response $response,
        array $args
    ) use ($players) {
        $programId = (int) $args['programId'];
        $data = (array) $request->getParsedBody();
        $players->create($programId, $data);

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/programs/{programId}/players/{playerId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($players) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $players->delete((int) $args['playerId']);
        } else {
            $players->update((int) $args['playerId'], $data);
        }

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/programs/{programId}/coaches', function (
        Request $request,
        Response $response,
        array $args
    ) use ($coaches) {
        $data = (array) $request->getParsedBody();
        $coaches->create((int) $args['programId'], $data['name'], $data['title'] ?? null, (int) ($data['sort_order'] ?? 0));

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/programs/{programId}/coaches/{coachId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($coaches) {
        $data = (array) $request->getParsedBody();
        if (($data['_method'] ?? '') === 'DELETE') {
            $coaches->delete((int) $args['coachId']);
        } else {
            $coaches->update((int) $args['coachId'], $data['name'], $data['title'] ?? null, (int) ($data['sort_order'] ?? 0));
        }

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
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

    $app->get('/games/new', function (Request $request, Response $response) use ($view, $sports, $divisions, $schools) {
        $html = $view->render('games/new', [
            'title' => 'New Game',
            'sports' => $sports->all(),
            'divisions' => $divisions->all(),
            'schools' => $schools->all(),
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

        $homeSchool = ($data['home_school_id'] ?? '') !== ''
            ? $schools->find((int) $data['home_school_id'])
            : $schools->findOrCreateByName($data['home_school_name'] ?? '', $data['home_mascot'] ?? null);
        $awaySchool = ($data['away_school_id'] ?? '') !== ''
            ? $schools->find((int) $data['away_school_id'])
            : $schools->findOrCreateByName($data['away_school_name'] ?? '', $data['away_mascot'] ?? null);

        $willamette = $schools->findByName('Willamette University');
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

        $gameRosters->createSnapshotFromProgram($gameId, (int) $homeProgram['id'], 'home');
        $gameRosters->createSnapshotFromProgram($gameId, (int) $awayProgram['id'], 'away');

        return $response->withHeader('Location', '/games/' . $gameId)->withStatus(302);
    });

    $app->get('/games/{id}', function (Request $request, Response $response, array $args) use ($view, $games) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        $html = $view->render('games/show', [
            'title' => 'Game — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
        ]);
        $response->getBody()->write($html);

        return $response;
    });

    $app->get('/games/{id}/roster/{side}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $gameRosters) {
        $game = $games->find((int) $args['id']);
        $side = $args['side'];
        if ($game === null || !in_array($side, ['home', 'away'], true)) {
            throw new HttpNotFoundException($request);
        }

        $snapshot = $gameRosters->findSnapshot((int) $game['id'], $side);

        $html = $view->render('games/roster', [
            'title' => ucfirst($side) . ' Roster — Game #' . $game['id'],
            'game' => $game,
            'side' => $side,
            'snapshot' => $snapshot,
            'players' => $snapshot !== null ? $gameRosters->playersFor((int) $snapshot['id']) : [],
            'coaches' => $snapshot !== null ? $gameRosters->coachesFor((int) $snapshot['id']) : [],
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
        $gameRosters->createPlayer((int) $data['snapshot_id'], $data);

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
            $gameRosters->updatePlayer((int) $args['playerId'], $data);
        }

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->post('/games/{id}/roster/{side}/coaches', function (
        Request $request,
        Response $response,
        array $args
    ) use ($gameRosters) {
        $data = (array) $request->getParsedBody();
        $gameRosters->createCoach((int) $data['snapshot_id'], $data['name'], $data['title'] ?? null, (int) ($data['sort_order'] ?? 0));

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
        $officials->create((int) $args['id'], $data['name'], $data['role'] ?? null, (int) ($data['sort_order'] ?? 0));

        return $response->withHeader('Location', $request->getHeaderLine('Referer'))->withStatus(302);
    });

    $app->get('/games/{id}/script', function (
        Request $request,
        Response $response,
        array $args
    ) use ($view, $games, $scriptTemplates, $contextBuilder, $renderer) {
        $game = $games->find((int) $args['id']);
        if ($game === null) {
            throw new HttpNotFoundException($request);
        }

        $template = $scriptTemplates->findForGame(
            (int) $game['sport_id'],
            $game['division_id'] !== null ? (int) $game['division_id'] : null,
            (bool) $game['is_neutral_site']
        );

        $renderedScript = null;
        $missingTemplateReason = null;

        if ($template === null) {
            $missingTemplateReason = 'No script template found for this sport/division/site combination yet.';
        } else {
            $blocks = json_decode($template['content_blocks'], true);
            $context = $contextBuilder->build($game);
            $renderedScript = $renderer->render($blocks, $context);
        }

        $html = $view->render('games/script', [
            'title' => 'Script — ' . $game['home_school_name'] . ' vs ' . $game['away_school_name'],
            'game' => $game,
            'renderedScript' => $renderedScript,
            'missingTemplateReason' => $missingTemplateReason,
        ]);
        $response->getBody()->write($html);

        return $response;
    });
};
