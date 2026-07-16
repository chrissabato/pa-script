# PA Script App — Plan

## Context

Willamette University Athletics PA announcers currently run every home game off a library of Google Sheets in one Drive folder — one spreadsheet per sport (Women's/Men's/Neutral Soccer, Women's/Men's Basketball, Baseball, Softball, Volleyball, Lacrosse). Each sheet holds a two-team roster table, a narrated PA announcer script, and (inconsistently) a small game-setup block. Critically, **the script text is not formula-driven** — team names, coach names, officials, and per-player starting-lineup lines are hand-retyped into the script every single game. Reading six of these sheets in full turned up the evidence directly: literal blank double-spaces where names belong, `#N/A` placeholder rows never filled in, a repeated/misordered "Batting 6th" in Softball, swapped position/year text in another row, and merged-cell PSA paragraphs duplicated 2-3x in the export. This is the actual pain: manual, error-prone, repetitive data entry under time pressure before every game.

(The Drive folder also has a "Swimming" Google Doc using a completely different live, event-by-event meet-script format. It's out of scope for this app — excluded per your call.)

The goal is a web app that replaces this: structured roster/coach/game data, one reusable script template per sport (with a `neutral` variant for soccer), and a rendered, presentable script — including a teleprompter mode for reading live at the mic — generated from that data instead of retyped by hand. A browser bookmarklet will let the announcer scrape an opponent's roster off a live roster page instead of typing 20-30 names in manually.

**Confirmed constraints (from the user):**
- All roster-based sports in v1 — one flexible schema/template engine, not one per sport. Swimming excluded.
- **Sports are gender-agnostic base entities** (Soccer, Basketball, Baseball, Softball, Volleyball, Lacrosse), not separate top-level sports per gender. "Men's"/"Women's" is a **division**, a small extensible lookup (add a new division — co-ed, JV, whatever — without restructuring anything), attached to a sport's teams and templates rather than baked into the sport list itself.
- App owns its own data (no live Google Sheets sync); rosters/coaches are edited in-app and differ every game.
- No auth for v1.
- Deploys to an existing **RHEL server running PHP 7.2**, with **PostgreSQL** available, and **Composer is allowed**.

This last point overrides the initially-considered Node/Next.js stack — the plan below targets PHP 7.2 + Postgres directly so what's built is what actually runs on the target server, not something requiring a rewrite before deploy.

## What the source sheets actually contain (confirmed by direct reads)

- **Soccer** (read: Women's, Men's, and Neutral-site versions): `Roster`, `Script`, `Setup` tabs. Roster = two-team side-by-side table (`#, NAME, POS, YEAR, HOMETOWN`, pronunciation on a second line, starter flag column, coaches block above). Script = welcome line, per-team coach/assistants line, officials line, anthem cue, sportsmanship PSA. Men's vs. Women's differ only in pronouns/rosters, not structure — confirming division belongs on the data, not the sport definition. The **Neutral** site case is genuinely different in structure: neither team gets "your Bearcats" home framing — both are announced as visiting teams (confirmed by reading a real Pacific Lutheran vs. Calvin script hosted at Willamette's field), independent of which division is playing.
- **Basketball** (read: Women's and Men's), **Volleyball, Lacrosse**: starting lineup announced per player as *"At {position}, a {year} from {hometown}, number {#}, {name}"*; reserves (if listed) get a plain *"Number {#}, {name}"*. Volleyball adds a walk-out music cue (`[start Joker and the Thief]`) as an inline script beat.
- **Baseball, Softball**: same per-player pattern but ordered by **batting order** (*"Leading off at {position}..."*, *"Batting 2nd at {position}..."*, ... *"Batting 9th..."*), plus a separate standalone **starting pitcher** callout outside the batting order. Umpires are announced with specific roles ("behind the plate", "at first base", "at third") rather than a flat officials list.

This confirms the design already validated across every sport: one **block-based template engine** (plain text blocks, named placeholders, and "loop over a list" blocks) covers all roster-sports, with differences (batting order vs. simple starters, role-specific officials, neutral framing, division-specific pronouns/rosters) expressed as data/config, not code forks.

## Tech stack

- **PHP 7.2 + Composer**, deployed to the existing RHEL server. No Node/JS build pipeline anywhere — Node isn't even installed on the local dev machine, and pure PHP + vanilla JS keeps local dev and the RHEL deploy identical in shape.
- **Slim 4** as a thin router/micro-framework (supports PHP 7.1+, so safely within 7.2). Avoids hand-rolling routing without pulling in something heavy like a full MVC framework.
- **Plain PHP templates** (`.phtml`, `<?php ?>` + `htmlspecialchars`) instead of Twig — Twig 3 requires PHP ≥7.2.5, which is close enough to the stated "PHP 7.2" that it's not worth the risk on an unfamiliar server's exact patch version. Plain templates are zero-dependency and perfectly adequate at this scale.
- **PDO (`pdo_pgsql`)** direct to Postgres with prepared statements behind small repository classes — no ORM. Avoids version-compatibility risk from heavier ORMs and keeps the SQL visible and simple for a schema this size.
- **Phinx** for migrations (Postgres adapter, PHP 7.2-compatible) — versioned schema changes instead of manual SQL scripts.
- **Vanilla JS** for the interactive bits: inline roster-editor fields (small `fetch` calls to JSON endpoints), and the teleprompter's auto-scroll (plain `requestAnimationFrame` loop, no library needed).
- **No auth for v1**, per your call — but the bookmarklet's import endpoint still gets a single shared **import token** (a random string in server config, embedded in the generated bookmarklet URL) so it isn't a fully open write endpoint reachable from any random webpage. This is a shared secret, not a login system — consistent with "no auth."
- Local dev: PHP's built-in server (`php -S localhost:8000`) against a local or shared dev Postgres instance — no Docker/XAMPP required unless preferred.

## Data model (Postgres, via Phinx migrations)

**Persistent roster data**
- `sports` (id, name, slug, uses_batting_order bool) — gender-agnostic: Soccer, Basketball, Baseball, Softball, Volleyball, Lacrosse. Six rows, not eight.
- `divisions` (id, name, slug) — a small open lookup, seeded with "Men's" and "Women's" but not hardcoded to just those two; add "Co-ed", "JV", etc. later with no schema change.
- `schools` (id, name, mascot, short_name)
- `programs` (id, school_id, sport_id, division_id nullable, is_willamette) — a school×sport×division combo (e.g. "Willamette Women's Soccer" and "Willamette Men's Soccer" are two `programs` rows sharing the same `sport_id`). `division_id` is nullable for sports with only one team at a school in practice (e.g. Baseball), so those don't carry a meaningless division.
- `players` (id, program_id, number, name, pronunciation, position, year, hometown, is_starter, batting_order nullable, sort_order, is_active)
- `coaches` (id, program_id, name, title, sort_order)

**Per-game snapshots** (opponent rosters are one-off imports; Willamette's own lineup can change last-minute — neither should mutate the persistent roster above)
- `games` (id, sport_id, date, time_of_day, home_program_id nullable, away_program_id nullable, is_neutral_site, neutral_site_note, venue, script_template_id, anthem_note, notes, status)
- `game_roster_snapshots` (id, game_id, program_id, side: home/away, captured_at)
- `game_roster_players`, `game_roster_coaches` (snapshot row copies, same shape as `players`/`coaches`, plus `source_player_id`/`source_coach_id` nullable)
- `officials` (id, game_id, name, role, sort_order) — normalized so the renderer can produce a correctly-punctuated "X, Y, and Z" list, and support baseball/softball's role-specific umpire phrasing

**Script templating**
- `script_templates` (id, sport_id, division_id nullable, site_variant: home/neutral, name, content_blocks JSONB, version, updated_at) — two independent axes instead of one flat "variant" enum: `division_id` (which gender/division's pronouns and phrasing) and `site_variant` (home game vs. neutral site framing). Soccer's 3 read sheets become 3 rows: (Soccer, Women's, home), (Soccer, Men's, home), (Soccer, null, neutral). Adding a future division (e.g. Men's Volleyball) is a new `divisions` + `programs` + `script_templates` row, never a schema change.
- Block shape (JSON): `{type: text, content}` | `{type: placeholder, key}` | `{type: loop, over: starters|reserves|battingOrder|officials, template: [...]}`. The `loop` block generalizes every sport's per-player lineup pattern (including baseball/softball's batting-order variant) without new schema.
- Placeholder resolvers live as a small **static PHP registry** (a class mapping key → resolver function against a render context), not a DB table — avoids overengineering; only promote to DB-editable if the template editor genuinely needs runtime-added placeholders later.

**Bookmarklet import staging**
- `import_batches` (id, source_url, raw_payload JSONB, status: pending/reviewed/discarded, created_at)

## Bookmarklet design

1. A settings page (`/bookmarklet`) shows a draggable bookmarklet link containing the site's import token, e.g. `javascript:(function(){var s=document.createElement('script');s.src='https://pa-script.example/bookmarklet/scraper.js?t=<token>';document.body.appendChild(s);})()`. Keeping the bookmarklet itself a thin loader means the real scraping logic can be fixed/updated server-side without anyone re-dragging a new bookmarklet.
2. `scraper.js` (hosted, served as a static/PHP-generated JS file) runs in the context of whatever roster page is open. It tries a Sidearm Sports adapter first (the common NCAA roster-page platform, fairly consistent card/table markup), then falls back to a generic `<table>` scanner matching Number/Name/Position/Year/Hometown-ish headers. If neither confidently matches, it grabs the raw visible text of the likely roster region rather than failing outright, so the review screen still has something to work with.
3. It `POST`s the scraped payload (with the import token) to `/api/import/stage.php`. The endpoint validates the token against server config, writes an `import_batches` row, and returns a redirect URL.
4. `scraper.js` opens `/import/review.php?batch={id}` in a new tab — an editable table of the scraped rows (fix typos, add pronunciation guides, etc.) where the user picks the destination (a specific game's `game_roster_snapshots`, or the persistent `programs` roster) and explicitly commits. **Nothing is written to real roster tables until this explicit commit** — the scrape only ever lands in staging first.

## Pages/routes

- `/` — dashboard: upcoming games, quick "new game" per sport.
- `/sports` — list of base sports; each expands to its divisions (e.g. Soccer → Women's, Men's).
- `/sports/{sport}/{division}/roster` — persistent Willamette roster/coach editor for that sport+division's `program`.
- `/sports/{sport}/templates/{templateId}/edit` — script block editor with a placeholder-picker sidebar (one editor per `script_templates` row, i.e. per sport+division+site-variant combo).
- `/games`, `/games/new` — list and setup wizard (sport, division, opponent, date/time, home/away/neutral, officials).
- `/games/{id}` — hub linking to home/away roster snapshots, script preview, and teleprompter.
- `/games/{id}/roster/{home|away}` — snapshot editors; the away side is where bookmarklet-reviewed imports land.
- `/games/{id}/script` — rendered, placeholder-resolved, print-friendly script.
- `/games/{id}/teleprompter` — large-font auto-scroll view: play/pause, speed slider, font-size control.
- `/bookmarklet`, `/import/review.php?batch={id}` — bookmarklet setup and scrape review.

## Build phases

1. **Scaffolding.** Composer init, Slim 4 + Phinx wired to Postgres, base layout, `php -S` local dev loop confirmed working end to end (a page hitting the DB and rendering).
2. **Core schema + template engine, proven on Soccer.** All tables above, including `divisions` and `programs.division_id`/`script_templates.division_id`; the block-rendering engine (text/placeholder/loop); seed the Soccer sport with Women's and Men's divisions plus the neutral-site template, transcribed from the real sheet text already captured. Build roster editor, game setup wizard, script preview. *Verify:* create a game for each division and for a neutral-site matchup, populate rosters, render the script, and diff it structurally against the real hand-edited sheet content quoted above.
3. **Extend to the other sports.** Because all of them were read in this session, this is now data/config work, not new architecture: add Basketball with its two divisions, single-division Baseball/Softball/Volleyball/Lacrosse, `battingOrder` handling for Baseball/Softball, role-specific officials phrasing, volleyball's walk-out-music beat, and seed one sample game per sport. *Verify:* same structural-diff check per sport against the real script text captured above.
4. **Teleprompter mode.** Vanilla-JS auto-scroll/play-pause/font-size UI over the same rendered script content. Low schema risk, high standalone value, good early win. *Verify:* manual browser exercise at multiple speeds/fonts on a long script.
5. **Bookmarklet + import pipeline.** Token-gated `scraper.js`, `/api/import/stage.php`, `import_batches`, `/import/review.php`, commit path into `game_roster_players`. *Verify:* run the bookmarklet against a real (or saved fixture) roster page, edit a scraped row, commit, confirm it lands correctly and the script re-renders.
6. **Deploy to the RHEL server.** Confirm the server's exact PHP 7.2.x patch version and available extensions (`pdo_pgsql` in particular) before shipping; run Phinx migrations against the real Postgres DB; smoke-test all routes there.

## Verification approach throughout

- `php -S localhost:8000` for interactive browser exercise at every phase; direct `psql` queries (or a lightweight admin view) to eyeball seeded/committed rows.
- Seed fixtures encode the **actual sheet text captured in this session** (quoted above per sport) — the pass/fail bar at each phase is "reseed, load the page, confirm the rendered script structurally matches the original," accounting for now-dynamic placeholders replacing what used to be hand-typed blanks or `#N/A` rows.
- Recommend a small unit-test pass (PHPUnit) specifically on the template-rendering engine (block resolution, loop expansion incl. batting order, missing-placeholder handling) since every sport depends on it and a regression there is easy to miss just by eyeballing one rendered page.
- Bookmarklet testing against a live third-party roster page carries markup-drift/availability risk — keep a saved static HTML fixture of a representative roster page for repeatable local testing, with occasional live spot-checks.

## Open items to confirm during build (not blocking, but worth resolving early)

- Exact PHP patch version and enabled extensions on the RHEL server (affects the Twig-vs-plain-templates call above, though plain templates sidestep this entirely).
- The soccer `Setup` tab's full field list was never fully retrievable (kept truncating on read) — confirm during Phase 2 seeding whether anything beyond Home/Away team name + game time needs a dedicated `games` column.
- The two 1/0 flag columns per player in the Roster tabs — one is clearly "starter"; the second flag's meaning (captain? active/inactive?) should be confirmed by opening the actual sheet in Sheets UI rather than the plain-text export, since flags without headers don't carry meaning through the text export.
