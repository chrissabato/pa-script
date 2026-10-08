// Roster-import panel (templates/partials/roster-import.phtml): asks the
// server to fetch the roster URL, parses the HTML here with
// PaScriptScrape (the bookmarklet's parser), and stages the result for
// review. Pasted HTML takes the same path when a site blocks the server.
(function () {
    'use strict';

    var panel = document.querySelector('.roster-import');
    if (!panel) {
        return;
    }

    var urlInput = panel.querySelector('input[name="roster_url"]');
    var fetchButton = panel.querySelector('.roster-import-fetch');
    var openLink = panel.querySelector('.roster-import-open');
    var status = panel.querySelector('.roster-import-status');
    var paste = panel.querySelector('.roster-import-paste');
    var stageForm = panel.querySelector('.roster-import-stage');

    function say(message, isError) {
        status.textContent = message;
        status.classList.toggle('notice-error', !!isError);
        status.hidden = false;
    }

    function parse(html) {
        return new DOMParser().parseFromString(html, 'text/html');
    }

    function serverFetch(url, programId) {
        return fetch('/roster/fetch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url, programId: programId || null })
        }).then(function (r) {
            return r.json();
        });
    }

    // Some sites (legacy Presto) list coaches only on a separate page;
    // fetch it too. Best-effort — the roster import goes ahead without it.
    function withCoaches(result, doc, sourceUrl) {
        var coachesUrl = result.coaches.length ? null : PaScriptScrape.coachesPageUrl(doc, sourceUrl);
        if (!coachesUrl) {
            return Promise.resolve(result);
        }
        say('Found ' + result.players.length + ' players — fetching the coaches page…');
        return serverFetch(coachesUrl).then(function (r) {
            if (r.ok) {
                result.coaches = PaScriptScrape.coachesFromPage(parse(r.html));
            }
            return result;
        }).catch(function () {
            return result;
        });
    }

    function stage(html, sourceUrl) {
        var doc = parse(html);
        var result = PaScriptScrape.run(doc, sourceUrl);
        if (!result.players.length && !result.coaches.length && !result.rawText.trim()) {
            say('No roster found in that page.', true);
            return Promise.resolve();
        }
        return withCoaches(result, doc, sourceUrl).then(function (result) {
            say(result.players.length
                ? 'Found ' + result.players.length + ' players and ' + result.coaches.length + ' coaches — opening review…'
                : 'No player list recognized — opening review with the page text…');
            stageForm.querySelector('input[name="scrape"]').value = JSON.stringify(result);
            stageForm.submit();
        });
    }

    urlInput.addEventListener('input', function () {
        openLink.href = urlInput.value;
        openLink.hidden = urlInput.value.trim() === '';
    });

    fetchButton.addEventListener('click', function () {
        var url = urlInput.value.trim();
        if (!url) {
            say('Enter the roster page URL first.', true);
            return;
        }
        fetchButton.disabled = true;
        say('Fetching roster…');

        serverFetch(url, panel.dataset.programId).then(function (r) {
            if (r.ok) {
                return stage(r.html, r.url);
            }
            say(r.error, true);
            if (r.blocked) {
                paste.open = true;
            }
        }).catch(function () {
            say('Could not reach the PA Script server.', true);
        }).then(function () {
            fetchButton.disabled = false;
        });
    });

    panel.querySelector('.roster-import-parse').addEventListener('click', function () {
        var html = paste.querySelector('textarea').value;
        if (!html.trim()) {
            say('Paste the page HTML first.', true);
            return;
        }
        stage(html, urlInput.value.trim());
    });
})();
