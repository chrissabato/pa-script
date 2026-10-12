# PA Script

A web app for college athletics public-address announcers, built for
Willamette University Athletics. It replaces the hand-edited Google Sheets (one per sport) used to run PA scripts
at home games: rosters, coaches, officials and game details are entered once
as data, and the app generates the announcer's script from them — on screen,
as a teleprompter, or printed.

Live at **https://athletics.willamette.edu/pa**. Other schools can run their
own copy — see [Installing at another school](#installing-at-another-school).

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
- **Teams** — schools, logos and mascots come read-only from the athletics
  Team Data API and sync automatically.

Script wording is transcribed from Willamette's real sheets for every sport
(Soccer, Basketball, Volleyball, Lacrosse, Softball, Baseball) except
neutral-site Soccer, which is still placeholder wording.

## Tech stack

- PHP 7.2 + Composer, [Slim 4](https://www.slimframework.com/), plain `.phtml`
  templates
- PostgreSQL via PDO (no ORM), [Phinx](https://phinx.org/) migrations
- Vanilla JavaScript, no build step
- Gemini API (over curl) for document fill

Production runs **PHP 7.2.24** and **Postgres 9.3.25**, older than a typical
dev machine — see [Compatibility](#compatibility).

## Getting started

Requirements: PHP 7.2+ with `pdo_pgsql`, `curl` and `zlib`; Composer;
PostgreSQL.

```sh
composer install
cp .env.example .env          # then set the DB_* values
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

## Installing at another school

The defaults are Willamette's. To run it for your own school:

1. **Teams.** Schools, mascots and logos come from a Team Data API and are
   read-only in the app. Willamette's
   ([docs](https://athletics.willamette.edu/api/teams/docs)) is public and
   lists Northwest Conference schools and other opponents; if your school
   and opponents are there, keep it. Otherwise host your own endpoint
   returning the same JSON and set `TEAMS_API_URL` to it.
2. **Your school.** Set `HOME_TEAM_SLUG` to your school's slug in that API
   (preselected as the home team) and `SITE_NAME` for the page header (in quotes: `SITE_NAME="Example Athletics"`).
3. **Timezone.** Set `APP_TIMEZONE`.
4. **Access.** Set `APP_PASSWORD`, `ADMIN_PASSWORD` and a long random
   `IMPORT_TOKEN`. **Delete the Shibboleth section of `public/.htaccess`**
   (Willamette's SSO rule — without Shibboleth installed Apache errors on
   it), or change its campus address block if you run Shibboleth too.
5. **Script wording.** The default templates, shared snippets (welcome,
   sportsmanship statement) and default venues are Willamette's. Edit them
   in the app under Templates (Admin in the nav); edits survive reseeding.
   The team list (`SportRepository::TEAMS`) is code.

Then follow [Getting started](#getting-started). The app runs on PHP 7.2+ and
PostgreSQL 9.3+, at a domain root or in a subfolder.

## Configuration

All settings live in `.env` (gitignored); `.env.example` documents each one.

| Variable | Purpose |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection (dev). |
| `DB_CREDS_FILE` | Production: read the DB credentials from the server's shared JSON file instead. |
| `DB_SCHEMA` | Production: keep the app's tables in their own schema (`pa_script`) of the shared database. |
| `APP_PASSWORD` | The single shared login password (no usernames). Unset = no login. Changing it logs everyone out. |
| `ADMIN_PASSWORD` | A second password for script templates, snippets and default venues (Admin in the nav). Unset = those pages are open to everyone. Changing it locks every admin out. |
| `IMPORT_TOKEN` | Shared secret embedded in the bookmarklet; the bookmarklet's endpoints check it instead of the login. |
| `GEMINI_API_KEY` | Enables "Fill in from a document". Unset = the panel says it isn't set up. |
| `GEMINI_MODEL`, `GEMINI_THINKING_LEVEL` | Optional; default `gemini-3.5-flash-lite` with `minimal` thinking (~3 s per document). |
| `SITE_NAME` | Page-header name (default `Willamette Athletics`). |
| `HOME_TEAM_SLUG` | Your school's slug in the Team Data API, preselected as the home team (Willamette: `willamette-university`). Unset = none preselected. |
| `TEAMS_API_URL` | Team Data API the schools sync from (default Willamette's, `https://athletics.willamette.edu/api/teams`). |
| `APP_BASE_PATH` | URL prefix the app is served under (production: `/pa`). Detected automatically when unset. |
| `APP_TIMEZONE`, `APP_DEBUG`, `APP_ENV` | Timezone for "today" (default `America/Los_Angeles`); error detail; environment name. |

## Access

- **Shibboleth** — `public/.htaccess` requires a Willamette SSO login off
  campus; on campus (158.104.0.0/16) is open. Other installs delete or
  adapt that section.
- **Shared password** — when `APP_PASSWORD` is set, every page asks for it
  once per browser (a 30-day cookie).
- **Admin password** — when `ADMIN_PASSWORD` is set, the script templates,
  snippets and default venues are hidden behind it too (Admin in the nav; a
  7-day cookie, Lock admin to clear it).
- **Exceptions** — the bookmarklet's cross-site endpoints
  (`/bookmarklet/scraper.js`, `/api/import/stage`) are exempt from both and
  gated by `IMPORT_TOKEN`. Any new endpoint called from another site must be
  added to the `.htaccess` exemption *and* `App\Auth\PasswordGate::OPEN_PATHS`.

## Deploying

Production: repo at `/www/athletics.willamette.edu/repos/pa-script`, with
`public/` symlinked to `htdocs/pa` (Apache + mod_php).

```sh
git pull
composer install --no-dev --optimize-autoloader
vendor/bin/phinx migrate -c config/phinx.php
vendor/bin/phinx seed:run -c config/phinx.php -s ScriptTemplatesSeeder   # only after template changes
```

Errors go to `/var/log/httpd/athletics.willamette.edu_error_ssl.log`.

Teams sync from the API at most hourly when the Teams or New game page
loads; `php bin/sync-teams.php` syncs on demand and is safe to run from cron.

## Development

```sh
vendor/bin/phpunit     # tests: rendering engine, sport scripts, roster URLs, document fill
vendor/bin/phpcs       # PHP 7.2 compatibility check — run before every deploy
php tests/scraper/run.php [fixture ...] [--json]   # replay saved roster pages through the scraper (needs google-chrome)
```

### Compatibility

Dev machines usually run much newer PHP and Postgres than production, so code
that works locally can break on deploy:

- **PHP 7.2** — run `vendor/bin/phpcs` (PHPCompatibility), which flags newer
  syntax. Production PHP has no https stream wrapper (use curl for outbound
  HTTP) and no zip extension. Composer's platform is pinned to PHP 7.2, which
  caps some package versions on purpose.
- **Postgres 9.3** — no `jsonb`, `ON CONFLICT`, `FILTER`,
  `ADD COLUMN IF NOT EXISTS`, ….

### Project layout

```
public/            front controller (index.php), .htaccess, css/app.css, js/
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

The app is served from a subfolder in production, so links never use a bare
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
