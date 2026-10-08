# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

Build phases 1–5 of `PLAN.md` are implemented (scaffolding, template engine, all six sports, teleprompter, bookmarklet + import pipeline); Phase 6 (deploy to RHEL) is next. `PLAN.md` remains the authoritative spec; this file summarizes its key decisions. Script template *wording* in the seeders is still placeholder, not the real sheet text.

## Commands

- Install deps: `composer install`
- Config: copy `.env.example` to `.env` (gitignored) — DB credentials + `IMPORT_TOKEN`.
- Migrations: `vendor/bin/phinx migrate -c config/phinx.php` — the config lives at `config/phinx.php`, **not** the default `phinx.yml`, so `-c` is always required (`rollback`, `status`, `create` too).
- Seeds (idempotent, safe to re-run): `vendor/bin/phinx seed:run -c config/phinx.php` (or `-s SportsSeeder` for one). `SchoolsSeeder` just runs the team API sync, so it needs network access.
- Sync teams from the API: `php bin/sync-teams.php` (cron-safe; the app also auto-syncs at most hourly when `/schools` or `/games/new` loads).
- Tests: `vendor/bin/phpunit` (suite in `tests/`, covers the rendering engine and roster-URL guessing).
- Roster scraper: `php tests/scraper/run.php [fixture ...] [--json]` replays `tests/fixtures/roster-pages/*.html` through `public/js/scraper-core.js` in headless Chrome (dev box only).
- Lint a file: `php -l path/to/file.php`
- Dev server: `php -S localhost:8000 -t public public/index.php` — the explicit router script is required, otherwise PHP's built-in server 404s extension-looking URLs before they reach Slim. On the shared dev box it already runs as the `pa-script-dev.service` systemd unit on port 8002, against the `pa-script-postgres` Docker container; check those before starting anything new.

## Code layout

- `public/index.php` — Slim front controller; `public/css/app.css` (single plain-CSS stylesheet), `public/js/` (teleprompter, `scraper-core.js` roster parser, `roster-import.js`).
- `src/Roster/` — `RosterUrlGuesser` (suggests a program's roster URL from the school's site + CMS provider), `RosterPageFetcher` (server-side fetch, public hosts only).
- `src/Routes/routes.php` — all routes; `src/Repository/` — one PDO repository per table; `src/Rendering/` — `TemplateRenderer`, `PlaceholderRegistry`, `GameContextBuilder`.
- `templates/` — `.phtml` views wrapped by `templates/layout.phtml`.
- `migrations/`, `seeds/` — Phinx.

Players are always listed by jersey number (numeric compare on the digits, unnumbered last) — there is deliberately no manual player sort field. Coaches and officials keep a `sort_order`.

## What this is

A web app for Willamette University Athletics PA announcers that replaces a library of hand-edited Google Sheets (one per sport) used to run PA scripts at home games. Today, team names, coach names, officials, and starting lineups are retyped by hand into a narrated script before every game — this app makes rosters/coaches/games structured data and generates the presentable script (including a teleprompter mode) from that data instead.

A companion browser bookmarklet scrapes an opponent's roster off a live roster page (e.g. Sidearm Sports platform) instead of the announcer typing 20-30 names in by hand.

## Tech stack (target, per PLAN.md)

- **PHP 7.2 + Composer**, deployed to an existing RHEL server. No Node/JS build pipeline — plain PHP + vanilla JS only, so local dev and RHEL deploy are shape-identical.
- **Slim 4** as router/micro-framework.
- **Plain `.phtml` templates** (`<?php ?>` + `htmlspecialchars`), not Twig — deliberately avoided due to Twig 3's PHP ≥7.2.5 requirement being too close to the server's stated PHP 7.2.
- **PDO (`pdo_pgsql`)** direct to Postgres with prepared statements behind small repository classes. No ORM.
- **Phinx** for Postgres migrations.
- **Vanilla JS** for interactive bits: inline roster-editor fields via small `fetch` calls to JSON endpoints, and the teleprompter's auto-scroll (`requestAnimationFrame`, no library).
- **No auth in v1.** The bookmarklet's import endpoint is instead gated by a single shared import token (random string in server config, embedded in the generated bookmarklet URL) — a shared secret, not a login system.
- Local dev: PHP's built-in server (`php -S localhost:8000`) against local/shared dev Postgres — no Docker/XAMPP required.

## Core architecture decisions (don't relitigate these)

- **Sports are gender-agnostic base entities** (Soccer, Basketball, Baseball, Softball, Volleyball, Lacrosse). "Men's"/"Women's" is a `division` — a small extensible lookup attached to a sport's teams/templates, not a separate top-level sport. Adding a division (co-ed, JV, ...) must never require restructuring the sport list.
- **One flexible template engine, not one template per sport.** Sport-specific differences (batting order vs. simple starters, role-specific officials phrasing, neutral-site framing, per-division pronouns) are expressed as **data/config** via block-based script templates, never as code forks or new schema.
- **Script templates vary along two independent axes**: `division_id` (whose pronouns/phrasing) and `site_variant` (`home` vs. `neutral`). E.g. soccer is 3 `script_templates` rows: (Soccer, Women's, home), (Soccer, Men's, home), (Soccer, null, neutral).
- **Template blocks** are JSON: `{type: text, content}` | `{type: placeholder, key}` | `{type: loop, over: starters|reserves|battingOrder|officials, template: [...]}`. The `loop` block generalizes every sport's per-player lineup pattern, including baseball/softball's batting-order variant.
- **Placeholder resolvers are a static PHP registry** (key → resolver function against a render context), not a DB table — only promote to DB-editable if a future template editor genuinely needs runtime-added placeholders.
- **The app owns its data; nothing syncs live from Google Sheets.** Rosters/coaches are edited in-app and differ every game.
- **Rosters exist only per game** (supersedes PLAN.md's persistent-roster design, removed 2026-10-08 because the two kinds of roster confused users). Each game side gets an empty roster (`game_roster_snapshots` + `game_roster_players`/`game_roster_coaches`) filled by a roster-URL fetch, the bookmarklet, or by hand. There are no standing `players`/`coaches` tables. A `program` (school × sport × division) is internal only: games point at one per side, and it remembers that team's `roster_url` for the next game. Don't reintroduce a user-facing standing roster.
- **Bookmarklet scrapes always land in staging first.** `scraper.js` POSTs to `/api/import/stage.php`, which writes an `import_batches` row. Nothing touches real roster tables until a human explicitly reviews and commits via `/import/review.php?batch={id}`. The bookmarklet itself is a thin loader (just carries the token); the actual scraping logic lives in server-hosted `scraper.js` so it can be fixed/updated without redistributing the bookmarklet.
- **Officials are normalized** (`officials` table: name, role, sort_order), not a flat string, so the renderer can produce correctly-punctuated "X, Y, and Z" lists and support baseball/softball's role-specific umpire phrasing ("behind the plate", "at first base", ...).

## Data model summary

See PLAN.md's "Data model" section for full column lists. Key tables:
- `sports`, `divisions`, `schools`, `programs` (school×sport×division; internal — game sides + saved `roster_url`).
- `schools` is a read-only local cache of the athletics Team Data API (https://athletics.willamette.edu/api/teams/docs), keyed by the API's `slug` (stable; names get edited). Only `App\Teams\TeamApiSync` writes it; teams are edited in the API's admin, never in this app. Sync keeps a team the API dropped if a program still references it. Find Willamette by slug `willamette-university` (`SchoolRepository::WILLAMETTE_SLUG`), not by name — the API also has a `willamette-university-gold` alt-logo entry with the same short name.
- `games`, `game_roster_snapshots`, `game_roster_players`, `game_roster_coaches`, `officials` — per-game data; the only rosters.
- `script_templates` (sport_id, division_id nullable, site_variant, content_blocks JSONB) — the template engine.
- `import_batches` — bookmarklet scrape staging.

## Routes

All defined in `src/Routes/routes.php`:
- `/` — dashboard (upcoming games); `/schools` (read-only teams from the API), `POST /schools/sync`
- `/games`, `/games/new`, `/games/{id}` (`POST` with `_method=DELETE` deletes), `/games/{id}/officials`, `/games/{id}/roster/{home|away}` (+ `/players`, `/coaches`), `/games/{id}/script`, `/games/{id}/teleprompter`
- `/bookmarklet`, `/bookmarklet/scraper.js`, `/api/import/stage` (token-gated), `/import/review/{batchId}` (+ `/commit`, `/discard`; `?game_id=&side=` preselects)
- Roster URL import (panel on each game roster page): `POST /programs/{programId}/roster-url`, `POST /roster/fetch` (server fetches the page, browser parses players + coaches with `scraper-core.js`, following a separate coaches page when the roster page has none), `POST /import/stage` (in-app staging → review). Bot-blocked sites fall back to the bookmarklet or pasted HTML — keep those fallbacks even though production is expected to be allowlisted by Presto.
- Not built yet: the template editor PLAN.md describes (`/sports/{sport}/templates/{templateId}/edit`) — templates are edited via `seeds/ScriptTemplatesSeeder.php`.

## Build order

Follow PLAN.md's phased build order: (1) scaffolding — Composer/Slim/Phinx wired to Postgres with one page round-tripping the DB; (2) core schema + template engine proven end-to-end on Soccer (including the neutral-site variant); (3) extend to remaining sports as data/config, not new architecture; (4) teleprompter; (5) bookmarklet + import pipeline; (6) deploy to the RHEL server. Each phase has an explicit verification step in the plan (structural diff against real captured sheet text) — follow it rather than eyeballing.

## Verification approach

- `php -S localhost:8000` for interactive browser exercise at every phase; `psql` or a lightweight admin view to eyeball seeded/committed rows.
- Seed fixtures should encode the real sheet text captured during planning (quoted in PLAN.md per sport) — the pass/fail bar is "reseed, load the page, confirm the rendered script structurally matches the original."
- PHPUnit tests on the template-rendering engine specifically (block resolution, loop expansion including batting order, missing-placeholder handling) are worth prioritizing since every sport depends on this engine.
- The bookmarklet should be tested against a saved static HTML fixture of a representative roster page for repeatable local testing, with occasional live spot-checks against markup drift.

## Dev environment vs. production

**The current build/dev environment is not the production RHEL server** and will have tools, PHP extensions, and versions production does not. Don't assume something works in production just because it works here — verify anything environment-sensitive (PHP extensions, CLI tools, PHP/Postgres versions, Composer packages with native extension dependencies) against the actual RHEL server, especially before Build phase 6 (deploy).

## Open items flagged in the plan (confirm before relying on assumptions here)

- Exact PHP patch version and enabled extensions on the target RHEL server (roster URL fetch needs `curl`).
- The soccer `Setup` tab's full field list beyond Home/Away team name + game time was never fully retrieved from the source sheet.
- The meaning of the second 1/0 roster flag column (beyond the "starter" flag) is unconfirmed — needs checking in the live Sheets UI, not the plain-text export.
