# PA Script

A web app for college athletics public-address announcers. It replaces
hand-edited spreadsheets (one per sport) used to run PA scripts at home
games: rosters, coaches, officials and game details are entered once as
data, and the app generates the announcer's script from them — on screen,
as a teleprompter, or printed. See [Setting up for your
school](#setting-up-for-your-school).

## What it does

- **Games** — create a game (sport, division, opponent, date, venue); each
  side gets its own roster for that game. A new game starts from the team's
  most recent roster this academic year.
- **Rosters** — fill a side's roster by:
  - fetching the team's roster page (the URL is guessed from the school's
    athletics site and remembered for next time),
  - the **bookmarklet**, run on a roster page in your own browser, for sites
    that block the server,
  - pasting the roster page's HTML, or typing it in.

  Imported rosters always go to a review page first; nothing changes until you
  commit.
- **Fill in from a document** — upload or drag in game notes or a
  pronunciation guide (PDF or Word .docx) and Gemini suggests what the roster
  is missing: pronunciations, coaches' seasons and pronouns, positions, class
  years, hometowns. You tick which suggestions to keep; filled-in fields are
  never overwritten.
- **Script** — generated per sport, division (Men's/Women's) and site
  (home/neutral) from block-based templates, with spoken forms ("first year",
  "Oregon", names with pronunciation guides). A list of data gaps (no
  starters marked, no coaches or officials, no game time) shows above it. A game's script can be
  customized by hand and reset back to the generated one.
- **Teleprompter** — full-screen auto-scrolling script (spacebar to play/pause).
- **Print** — rosters side by side on a portrait page, optionally starters only.
- **Teams** — schools, logos and mascots come read-only from a Team Data
  API ([opponent-api](https://github.com/chrissabato/opponent-api)) and sync
  automatically.

Default script wording is included for Soccer, Basketball, Volleyball,
Lacrosse, Softball and Baseball (neutral-site Soccer is still placeholder
wording); every school edits it to its own in the app.

## Tech stack

- PHP 7.2 + Composer, [Slim 4](https://www.slimframework.com/), plain `.phtml`
  templates
- PostgreSQL via PDO (no ORM), [Phinx](https://phinx.org/) migrations
- Vanilla JavaScript, no build step
- Gemini API (over curl) for document fill

It deliberately supports **PHP 7.2** and **Postgres 9.3**, older than a
typical dev machine — see [Compatibility](#compatibility).

## Getting started

Requirements: PHP 7.2+ with `pdo_pgsql`, `curl` and `zlib`; Composer;
PostgreSQL.

```sh
composer install
cp .env.example .env          # then set the DB_* values and TEAMS_API_URL
cp public/.htaccess.example public/.htaccess   # Apache only
vendor/bin/phinx migrate -c config/phinx.php
vendor/bin/phinx seed:run -c config/phinx.php   # sports, divisions, script templates, teams (needs network)
php -S localhost:8000 -t public public/index.php
```

Then open http://localhost:8000.

The router script (`public/index.php`) on the `php -S` line is required —
without it PHP's built-in server 404s URLs that look like files
(`/bookmarklet/scraper.js`) before they reach the app.

Phinx's config lives at `config/phinx.php`, not the default `phinx.yml`, so
every `phinx` command needs `-c config/phinx.php`.

## Setting up for your school

To run it for your own school:

1. **Teams (required).** Schools, mascots and logos come from a Team Data
   API and are read-only in the app; set `TEAMS_API_URL` to one — there is
   no default, and teams don't sync without it. Run your own with
   [opponent-api](https://github.com/chrissabato/opponent-api).
2. **Your school.** Set `HOME_TEAM_SLUG` to your school's slug in that API
   (preselected as the home team) and `SITE_NAME` for the page header (in
   quotes: `SITE_NAME="Example Athletics"`).
3. **Timezone.** Set `APP_TIMEZONE`.
4. **Access.** Set `APP_PASSWORD`, `ADMIN_PASSWORD` and a long random
   `IMPORT_TOKEN`. On Apache, copy `public/.htaccess.example` to
   `public/.htaccess` and add any SSO or IP rules of your own there (it's
   gitignored, so `git pull` leaves it alone).
5. **Script wording.** The default templates, shared snippets (welcome,
   sportsmanship statement) and default venues are another school's. Edit
   them in the app under Templates (Admin in the nav); edits survive
   reseeding.
   The team list (`SportRepository::TEAMS`) is code.

Then follow [Getting started](#getting-started). The app runs on PHP 7.2+ and
PostgreSQL 9.3+, at a domain root or in a subfolder.

## Configuration

All settings live in `.env` (gitignored); `.env.example` documents each one.

| Variable | Purpose |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection. |
| `DB_CREDS_FILE` | Optional: read the DB credentials from a JSON file (keys `DB_HOST`, `DB_PORT`, …) instead. |
| `DB_SCHEMA` | Optional: keep the app's tables in their own schema of a shared database (default `public`). |
| `APP_PASSWORD` | The single shared login password (no usernames). Unset = no login. Changing it logs everyone out. |
| `ADMIN_PASSWORD` | A second password for script templates, snippets and default venues (Admin in the nav). Unset = those pages are open to everyone. Changing it locks every admin out. |
| `IMPORT_TOKEN` | Shared secret embedded in the bookmarklet; the bookmarklet's endpoints check it instead of the login. |
| `GEMINI_API_KEY` | Enables "Fill in from a document". Unset = the panel says it isn't set up. |
| `GEMINI_MODEL`, `GEMINI_THINKING_LEVEL` | Optional; default `gemini-3.5-flash-lite` with `minimal` thinking (~3 s per document). |
| `SITE_NAME` | Shown before "PA Script" in the page header. Quote values with spaces. |
| `HOME_TEAM_SLUG` | Your school's slug in the Team Data API, preselected as the home team Unset = none preselected. |
| `TEAMS_API_URL` | **Required.** Team Data API the schools sync from (run your own with [opponent-api](https://github.com/chrissabato/opponent-api)). Unset = no team sync. |
| `APP_BASE_PATH` | URL prefix the app is served under (e.g. `/pa`). Detected automatically when unset. |
| `APP_TIMEZONE`, `APP_DEBUG`, `APP_ENV` | Timezone for "today" (default `America/Los_Angeles`); error detail; environment name. |

## Access

- **Server rules** — `public/.htaccess` is per server and gitignored (start
  from `public/.htaccess.example`), so an install can add its own SSO or IP
  restrictions there.
- **Shared password** — when `APP_PASSWORD` is set, every page asks for it
  once per browser (a 30-day cookie).
- **Admin password** — when `ADMIN_PASSWORD` is set, the script templates,
  snippets and default venues are hidden behind it too (Admin in the nav; a
  7-day cookie, Lock admin to clear it).
- **Exceptions** — the bookmarklet's cross-site endpoints
  (`/bookmarklet/scraper.js`, `/api/import/stage`) are exempt from both and
  gated by `IMPORT_TOKEN`. Any new endpoint called from another site must be
  added to `App\Auth\PasswordGate::OPEN_PATHS`, `public/.htaccess.example`'s
  note, and any server's own `.htaccess` exemption.

## Deploying

Serve `public/` as the web root, or symlink it into one to run under a
subfolder (Apache + mod_php; `public/.htaccess` from the example). To update:

```sh
git pull
composer install --no-dev --optimize-autoloader
vendor/bin/phinx migrate -c config/phinx.php
vendor/bin/phinx seed:run -c config/phinx.php -s ScriptTemplatesSeeder   # only after template changes
```

Teams sync from the API at most hourly when the Teams or New game page
loads; `php bin/sync-teams.php` syncs on demand and is safe to run from cron.

## Development

```sh
vendor/bin/phpunit     # tests: rendering engine, sport scripts, roster URLs, document fill
vendor/bin/phpcs       # PHP 7.2 compatibility check — run before every deploy
php tests/scraper/run.php [fixture ...] [--json]   # replay saved roster pages through the scraper (needs google-chrome)
```

### Compatibility

The app supports old servers (PHP 7.2, Postgres 9.3), and dev machines usually
run much newer versions, so code that works locally can break on deploy:

- **PHP 7.2** — run `vendor/bin/phpcs` (PHPCompatibility), which flags newer
  syntax. Don't rely on PHP's https stream wrapper (use curl for outbound
  HTTP) or the zip extension. Composer's platform is pinned to PHP 7.2, which
  caps some package versions on purpose.
- **Postgres 9.3** — no `jsonb`, `ON CONFLICT`, `FILTER`,
  `ADD COLUMN IF NOT EXISTS`, ….

### Project layout

```
public/            front controller (index.php), .htaccess.example, css/app.css, js/
src/Routes/        routes.php — every route
src/Repository/    one PDO repository per table
src/Rendering/     template engine: TemplateRenderer, PlaceholderRegistry,
                   GameContextBuilder, Spoken (spoken forms), ScriptChecks
src/Roster/        roster URL guessing and server-side page fetching
src/Fill/          document fill: Gemini client, .docx text extraction
src/Auth/          shared-password middleware
src/Teams/         Team Data API sync
src/Templates/     default template wording, template editor syntax + field names
templates/         .phtml views, wrapped by layout.phtml
migrations/ seeds/ Phinx; ScriptTemplatesSeeder writes src/Templates/DefaultTemplates
tests/             PHPUnit tests, roster-page and document fixtures, scraper harness
```

The app can be served from a subfolder, so links never use a bare
`/path`: templates write `<?= $this->base() ?>/games`, PHP uses
`App\View\Url::to()`, and JavaScript takes URLs from `data-*` attributes or
form actions.

### Script templates

Each `script_templates` row is a sport × division (or all divisions) × site
(home / neutral), with content as JSON blocks: text, placeholders, loops over
starters / reserves / batting order / officials, and `when` conditionals.
Sport differences are data, not code. Announcers edit templates in the app
(Templates in the nav, `/templates/{id}`): the blocks are shown as text with
tags — `{venue}`, `[if venue]…[else]…[/if]`, `[each home starters]…[/each]`
(`App\Templates\TemplateSyntax`, friendly names in `FieldCatalog`) — with a
live preview against one of the sport's games. The default wording lives in
`src/Templates/DefaultTemplates.php`; `ScriptTemplatesSeeder` writes it to
every template that hasn't been edited in the app (`edited_at` is null), and
the editor's Reset to default puts it back. Wording every template shares
lives in global snippets (`[snippet Welcome]`, `[snippet Sportsmanship
statement]`), edited and added on the same page, which also sets each sport's
default venue — what `{venue}` reads at a home game with no venue typed in. To port a sport from its Google Sheet, download the sheet as
.xlsx and read the Script tab's formulas (the Drive text view only samples
rows), then pin the result with a test like
`tests/Rendering/WomensSoccerScriptTest.php`.

## License

[MIT](LICENSE).

## More

- [`PLAN.md`](PLAN.md) — the original design spec and build phases.
- [`CLAUDE.md`](CLAUDE.md) — condensed architecture decisions and conventions.
