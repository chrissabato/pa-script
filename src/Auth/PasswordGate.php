<?php

declare(strict_types=1);

namespace App\Auth;

use App\View\Url;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * One shared password (APP_PASSWORD) for the whole app — no usernames.
 * Logging in sets a cookie holding an HMAC of the password, so there is no
 * server-side session to expire and changing the password logs everyone out.
 * With APP_PASSWORD unset the gate is off (local dev). AdminGate reuses the
 * cookie handling with its own password, cookie name and lifetime.
 */
class PasswordGate implements MiddlewareInterface
{
    private const COOKIE = 'pa_script_auth';
    private const LIFETIME = 30 * 86400;

    /**
     * Reachable without logging in: the login form, and the bookmarklet's
     * cross-site endpoints, which carry no cookie and check IMPORT_TOKEN
     * instead (the same exemption public/.htaccess makes for Shibboleth).
     */
    private const OPEN_PATHS = ['/login', '/bookmarklet/scraper.js', '/api/import/stage'];

    /** @var string */
    private $password;

    /** @var string */
    private $cookieName;

    /** @var string */
    private $label;

    /** @var int */
    private $lifetime;

    public function __construct(
        string $password,
        string $cookieName = self::COOKIE,
        string $label = 'pa-script-login',
        int $lifetime = self::LIFETIME
    ) {
        $this->password = $password;
        $this->cookieName = $cookieName;
        $this->label = $label;
        $this->lifetime = $lifetime;
    }

    public function enabled(): bool
    {
        return $this->password !== '';
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->enabled() || $this->isLoggedIn($request) || in_array(self::appPath($request), self::OPEN_PATHS, true)) {
            return $handler->handle($request);
        }

        // Autosave and roster fetches can't follow a redirect to a form.
        if ($request->getHeaderLine('X-Requested-With') === 'fetch') {
            return (new SlimResponse())->withStatus(401);
        }

        $next = $request->getMethod() === 'GET' ? self::appPath($request) . self::query($request) : '/';

        return (new SlimResponse())
            ->withHeader('Location', Url::to('/login') . ($next !== '/' ? '?next=' . rawurlencode($next) : ''))
            ->withStatus(302);
    }

    public function isLoggedIn(Request $request): bool
    {
        $cookie = (string) ($request->getCookieParams()[$this->cookieName] ?? '');

        return $this->enabled() && $cookie !== '' && hash_equals($this->token(), $cookie);
    }

    public function check(string $password): bool
    {
        return $this->enabled() && hash_equals($this->password, $password);
    }

    public function logIn(Request $request, Response $response): Response
    {
        return $response->withAddedHeader('Set-Cookie', $this->cookie($request, $this->token(), $this->lifetime));
    }

    public function logOut(Request $request, Response $response): Response
    {
        return $response->withAddedHeader('Set-Cookie', $this->cookie($request, '', -86400));
    }

    /** Only same-app paths, so ?next= can't send someone off-site. */
    public static function safeNext(string $next): string
    {
        return preg_match('#^/(?![/\\\\])#', $next) === 1 ? $next : '/';
    }

    private function token(): string
    {
        return hash_hmac('sha256', $this->label, $this->password);
    }

    /**
     * Built by hand: PHP 7.2's setcookie() has no SameSite option.
     */
    private function cookie(Request $request, string $value, int $lifetime): string
    {
        $parts = [
            $this->cookieName . '=' . $value,
            'Path=' . (Url::base() === '' ? '/' : Url::base() . '/'),
            'Max-Age=' . max(0, $lifetime),
            'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + $lifetime),
            'HttpOnly',
            'SameSite=Lax',
        ];
        if ($request->getUri()->getScheme() === 'https') {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    /** The request path without the base ('/pa/games/3' → '/games/3'). */
    public static function appPath(Request $request): string
    {
        $path = $request->getUri()->getPath();
        $base = Url::base();
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }

        return $path === '' ? '/' : $path;
    }

    public static function query(Request $request): string
    {
        $query = $request->getUri()->getQuery();

        return $query === '' ? '' : '?' . $query;
    }
}
