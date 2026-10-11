<?php

declare(strict_types=1);

namespace App\Auth;

use App\View\Renderer;
use App\View\Url;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * A second shared password (ADMIN_PASSWORD) for the pages standard users
 * shouldn't touch: script templates, snippets and default venues. Runs
 * inside PasswordGate, so admins have logged in to the app as well. Same
 * cookie scheme as PasswordGate (an HMAC of the password; changing it locks
 * every admin out), lasting a week. With ADMIN_PASSWORD unset those pages
 * are open to everyone who's logged in, as in dev.
 *
 * It also tells the views whether the admin pages are open (the nav shows
 * Templates only then).
 */
class AdminGate implements MiddlewareInterface
{
    private const ADMIN_PATHS = '#^/(templates|snippets)(/|$)|^/sports/\d+/default-venue$#';

    /** @var PasswordGate */
    private $cookies;

    /** @var Renderer */
    private $view;

    public function __construct(string $password, Renderer $view)
    {
        $this->cookies = new PasswordGate($password, 'pa_script_admin', 'pa-script-admin', 7 * 86400);
        $this->view = $view;
    }

    public function enabled(): bool
    {
        return $this->cookies->enabled();
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $unlocked = $this->isUnlocked($request);
        $this->view->share(['adminEnabled' => $this->enabled(), 'adminUnlocked' => $unlocked]);

        $path = PasswordGate::appPath($request);
        if ($unlocked || !preg_match(self::ADMIN_PATHS, $path)) {
            return $handler->handle($request);
        }

        // Preview and venue autosave can't follow a redirect to a form.
        if ($request->getHeaderLine('X-Requested-With') === 'fetch' || $request->getMethod() !== 'GET') {
            return (new SlimResponse())->withStatus(403);
        }

        return (new SlimResponse())
            ->withHeader('Location', Url::to('/admin/login') . '?next=' . rawurlencode($path . PasswordGate::query($request)))
            ->withStatus(302);
    }

    public function isUnlocked(Request $request): bool
    {
        return !$this->enabled() || $this->cookies->isLoggedIn($request);
    }

    public function check(string $password): bool
    {
        return $this->cookies->check($password);
    }

    public function unlock(Request $request, Response $response): Response
    {
        return $this->cookies->logIn($request, $response);
    }

    public function lock(Request $request, Response $response): Response
    {
        return $this->cookies->logOut($request, $response);
    }
}
