// Core roster-scraping logic, developed/verified against real captured
// markup from two live Sidearm-hosted schools (see
// tests/fixtures/roster-pages/ in the pa-script repo) plus a synthetic
// generic-<table> fixture and a raw-text-fallback fixture.
//
// Exposed as PaScriptScrape.run() so it can be exercised headlessly
// against local fixtures before being embedded (unmodified) into the
// PHP-served bookmarklet endpoint.
var PaScriptScrape = (function () {
    'use strict';

    function text(el) {
        return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function longestText(nodeList) {
        var best = '';
        Array.prototype.forEach.call(nodeList, function (el) {
            var t = text(el);
            if (t.length > best.length) {
                best = t;
            }
        });
        return best;
    }

    // Classic Sidearm Sports template — <ul class="sidearm-roster-players">
    // of <li class="sidearm-roster-player">. Verified against a live
    // captured Calvin University page.
    function scrapeSidearmClassic() {
        var rows = document.querySelectorAll('li.sidearm-roster-player');
        if (!rows.length) {
            return null;
        }
        var players = [];
        rows.forEach(function (row) {
            var name = text(row.querySelector('.sidearm-roster-player-name a'))
                || text(row.querySelector('.sidearm-roster-player-name'));
            if (!name) {
                return;
            }
            players.push({
                number: text(row.querySelector('.sidearm-roster-player-jersey-number')),
                name: name,
                position: text(row.querySelector('.sidearm-roster-player-position .text-bold'))
                    || text(row.querySelector('.sidearm-roster-player-position')),
                year: longestText(row.querySelectorAll('.sidearm-roster-player-academic-year')),
                hometown: text(row.querySelector('.sidearm-roster-player-hometown')),
                pronunciation: ''
            });
        });
        return players.length ? players : null;
    }

    // Newer Nuxt-based Sidearm template — <div class="roster-card">.
    // Verified against a live captured Willamette University page. This
    // card view does not render hometown, so that field is typically left
    // blank for the human reviewer to fill in.
    function scrapeSidearmCards() {
        var rows = document.querySelectorAll('.roster-card');
        if (!rows.length) {
            return null;
        }
        var players = [];
        rows.forEach(function (row) {
            var name = text(row.querySelector('.roster-card__title-link'))
                || text(row.querySelector('.roster-card__title'));
            if (!name) {
                return;
            }
            players.push({
                number: text(row.querySelector('.roster-card__jersey-number')),
                name: name,
                position: text(row.querySelector('.roster-card__position')),
                year: text(row.querySelector('[class*="profile-field__value--class"]')),
                hometown: text(row.querySelector('[class*="profile-field__value--hometown"]')),
                pronunciation: ''
            });
        });
        return players.length ? players : null;
    }

    // Photo-grid Sidearm template — <div class="player-card"> /
    // <div class="player-card-wrapper">. Best-effort only: unlike a
    // dedicated server-side scraper we don't chase a same-origin
    // "?view=list" companion page for extra fields, so position/year/
    // hometown are commonly blank here and left for manual review.
    function scrapeSidearmPlayerCards() {
        var rows = document.querySelectorAll('.player-card-wrapper, .player-card');
        if (!rows.length) {
            return null;
        }
        var players = [];
        rows.forEach(function (row) {
            var number = text(row.querySelector('.number')) || text(row.querySelector('.jersey-number'));
            number = number.replace(/^#/, '');

            var name = text(row.querySelector('.player-name'));
            if (!name) {
                var first = text(row.querySelector('.firstname'));
                var last = text(row.querySelector('.lastname')).split(' - ')[0];
                name = (first + ' ' + last).trim();
            }
            if (!name) {
                return;
            }
            players.push({
                number: number,
                name: name,
                position: '',
                year: '',
                hometown: '',
                pronunciation: ''
            });
        });
        return players.length ? players : null;
    }

    // Generic fallback for non-Sidearm roster pages: any <table> whose
    // header row has a recognizable "Name" column.
    function scrapeGenericTable() {
        var tables = document.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            var table = tables[i];
            var headerCells = table.querySelectorAll('thead th, thead td');
            if (!headerCells.length) {
                var firstRow = table.querySelector('tr');
                headerCells = firstRow ? firstRow.querySelectorAll('th, td') : [];
            }
            var headers = Array.prototype.map.call(headerCells, function (th) {
                return text(th).toLowerCase();
            });

            var nameIdx = headers.findIndex(function (h) { return /name/.test(h); });
            if (nameIdx === -1) {
                continue;
            }
            var numIdx = headers.findIndex(function (h) { return /^no\.?$|number|^#$/.test(h); });
            var posIdx = headers.findIndex(function (h) { return /pos/.test(h); });
            var yearIdx = headers.findIndex(function (h) { return /year|^yr\.?$|class/.test(h); });
            var hometownIdx = headers.findIndex(function (h) { return /hometown/.test(h); });

            var bodyRows = table.querySelectorAll('tbody tr');
            if (!bodyRows.length) {
                continue;
            }
            var players = [];
            bodyRows.forEach(function (tr) {
                var cells = tr.querySelectorAll('td');
                if (!cells.length) {
                    return;
                }
                var name = nameIdx >= 0 && cells[nameIdx] ? text(cells[nameIdx]) : '';
                if (!name) {
                    return;
                }
                players.push({
                    number: numIdx >= 0 && cells[numIdx] ? text(cells[numIdx]) : '',
                    name: name,
                    position: posIdx >= 0 && cells[posIdx] ? text(cells[posIdx]) : '',
                    year: yearIdx >= 0 && cells[yearIdx] ? text(cells[yearIdx]) : '',
                    hometown: hometownIdx >= 0 && cells[hometownIdx] ? text(cells[hometownIdx]) : '',
                    pronunciation: ''
                });
            });
            if (players.length) {
                return players;
            }
        }
        return null;
    }

    function grabRawText() {
        var candidates = ['[class*="roster"]', 'main', 'body'];
        for (var i = 0; i < candidates.length; i++) {
            var el = document.querySelector(candidates[i]);
            if (el) {
                return text(el).slice(0, 20000);
            }
        }
        return '';
    }

    function run() {
        var adapters = [
            ['sidearm-classic', scrapeSidearmClassic],
            ['sidearm-cards', scrapeSidearmCards],
            ['sidearm-player-cards', scrapeSidearmPlayerCards],
            ['table', scrapeGenericTable]
        ];

        var players = null;
        var scrapedVia = 'rawtext';
        for (var i = 0; i < adapters.length; i++) {
            var result = adapters[i][1]();
            if (result) {
                players = result;
                scrapedVia = adapters[i][0];
                break;
            }
        }

        return {
            sourceUrl: window.location.href,
            scrapedVia: scrapedVia,
            players: players || [],
            rawText: players ? '' : grabRawText()
        };
    }

    return { run: run };
})();
