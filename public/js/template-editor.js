// Script template editor (templates/templates/edit.phtml): colours the
// tags in the textarea (a <pre> mirror drawn underneath transparent
// textarea text), inserts fields from the sidebar, and re-renders the
// preview against the chosen game as you type. The tag grammar matches
// App\Templates\TemplateSyntax; the server parses and reports errors.
(function () {
    'use strict';

    var form = document.getElementById('template-editor');
    if (!form) {
        return;
    }
    var textarea = form.querySelector('textarea[name="text"]');
    var highlight = form.querySelector('.template-highlight');
    var gameSelect = form.querySelector('[data-preview-game]');
    var output = form.querySelector('[data-preview-output]');
    var errorBox = form.querySelector('[data-preview-error]');
    var status = form.querySelector('[data-template-status]');
    var TOKEN = /(\[\[|\{\{|\{[^{}\n]*\}|\[(?:if\s[^\]\n]*|else|\/if|each\s[^\]\n]*|\/each|snippet\s[^\]\n]*)\])/i;
    // After a save that didn't parse, the textarea holds the unsaved text.
    var original = form.hasAttribute('data-unsaved') ? null : textarea.value;
    var previewTimer = null;
    var previewSeq = 0;

    function escapeHtml(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function tokenClass(token) {
        if (token === '[[' || token === '{{') {
            return 'tok-escape';
        }
        if (token.charAt(0) === '{') {
            return 'tok-field';
        }
        if (/^\[snippet/i.test(token)) {
            return 'tok-snippet';
        }
        return /^\[\/?each/i.test(token) ? 'tok-each' : 'tok-if';
    }

    function renderHighlight() {
        var parts = textarea.value.split(TOKEN);
        var html = '';
        for (var i = 0; i < parts.length; i++) {
            html += i % 2 === 0
                ? escapeHtml(parts[i])
                : '<span class="' + tokenClass(parts[i]) + '">' + escapeHtml(parts[i]) + '</span>';
        }
        // A trailing newline needs something after it to take up a line.
        highlight.innerHTML = html + ' ';
        syncScroll();
    }

    function syncScroll() {
        highlight.scrollTop = textarea.scrollTop;
        highlight.scrollLeft = textarea.scrollLeft;
    }

    function setStatus(text) {
        status.textContent = text;
    }

    function isDirty() {
        return textarea.value !== original;
    }

    function selectLine(line) {
        var lines = textarea.value.split('\n');
        var start = 0;
        for (var i = 0; i < line - 1 && i < lines.length; i++) {
            start += lines[i].length + 1;
        }
        textarea.focus();
        textarea.setSelectionRange(start, start + (lines[line - 1] || '').length);
    }

    function showError(message, line) {
        errorBox.innerHTML = '';
        errorBox.appendChild(document.createTextNode('Line ' + line + ': ' + message + ' '));
        var link = document.createElement('a');
        link.href = '#';
        link.textContent = 'Show me';
        link.addEventListener('click', function (event) {
            event.preventDefault();
            selectLine(line);
        });
        errorBox.appendChild(link);
        errorBox.hidden = false;
    }

    function preview() {
        var seq = ++previewSeq;
        var body = new URLSearchParams();
        body.set('text', textarea.value);
        body.set('game_id', gameSelect.value);

        fetch(form.dataset.previewUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (result) {
            // A slow response to older text mustn't overwrite a newer one.
            if (seq !== previewSeq) {
                return;
            }
            if (result.error) {
                showError(result.error, result.line);
                output.classList.add('is-stale');
                return;
            }
            errorBox.hidden = true;
            output.classList.remove('is-stale');
            output.textContent = result.script;
        }).catch(function () {
            if (seq === previewSeq) {
                errorBox.textContent = 'Preview unavailable — check your connection.';
                errorBox.hidden = false;
            }
        });
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(preview, 350);
    }

    // Replaces the selection, keeping it selected when it was wrapped so a
    // second wrap (e.g. if inside each) works on the same text.
    function insert(open, close) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selected = textarea.value.slice(start, end);
        var replacement = open + selected + (close || '');
        textarea.focus();
        textarea.setRangeText(replacement, start, end, 'end');
        if (close) {
            var innerStart = start + open.length;
            textarea.setSelectionRange(innerStart, innerStart + selected.length);
        }
        textarea.dispatchEvent(new Event('input'));
    }

    form.querySelectorAll('[data-insert]').forEach(function (button) {
        button.addEventListener('click', function () {
            insert(button.dataset.insert, '');
        });
    });
    form.querySelectorAll('[data-wrap-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            insert(button.dataset.wrapOpen, button.dataset.wrapClose);
        });
    });

    textarea.addEventListener('input', function () {
        renderHighlight();
        setStatus(isDirty() ? 'Unsaved changes' : '');
        schedulePreview();
    });
    textarea.addEventListener('scroll', syncScroll);
    gameSelect.addEventListener('change', preview);

    // Ctrl/Cmd+S saves.
    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });

    var submitting = false;
    form.addEventListener('submit', function () {
        submitting = true;
    });
    window.addEventListener('beforeunload', function (event) {
        if (!submitting && isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // The textarea and its mirror must wrap identically; re-sync if the
    // layout changes width.
    if (window.ResizeObserver) {
        new ResizeObserver(syncScroll).observe(textarea);
    }

    renderHighlight();
    preview();
}());
