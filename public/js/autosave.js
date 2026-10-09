// Row forms marked data-autosave (roster players/coaches, officials) save
// themselves: checkboxes and selects on change, text fields when you leave
// them, Enter in a field right away — no Save button, no page reload. The
// row's status cell shows Saving… / Saved / Not saved. Delete buttons still
// submit normally (confirm + reload).
//
// Rows are <tr><form>…</form><td>…</tr>, so the parser hoists each form out
// and its inputs are associated by the form pointer rather than nested —
// hence form.elements and per-element listeners instead of delegation.
(function () {
    'use strict';

    function isDelete(button) {
        return button && button.name === '_method' && button.value === 'DELETE';
    }

    document.querySelectorAll('form[data-autosave]').forEach(function (form) {
        var status = document.querySelector('[data-autosave-status="' + form.id + '"]');
        var inFlight = false;
        var queued = false;
        var fadeTimer = null;

        function show(state, text) {
            if (!status) {
                return;
            }
            clearTimeout(fadeTimer);
            status.textContent = text;
            status.className = 'autosave-status is-' + state;
            if (state === 'saved') {
                fadeTimer = setTimeout(function () {
                    status.textContent = '';
                }, 2000);
            }
        }

        function save() {
            // One request per row at a time; a change made mid-save is sent
            // right after, with the row's latest values.
            if (inFlight) {
                queued = true;
                return;
            }
            inFlight = true;
            show('saving', 'Saving…');

            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: new URLSearchParams(new FormData(form)),
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                show('saved', 'Saved');
            }).catch(function () {
                show('error', 'Not saved — try again');
            }).then(function () {
                inFlight = false;
                if (queued) {
                    queued = false;
                    save();
                }
            });
        }

        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.type !== 'submit' && el.type !== 'button') {
                el.addEventListener('change', save);
            }
        });

        form.addEventListener('submit', function (event) {
            if (isDelete(event.submitter)) {
                return;
            }
            event.preventDefault();
            save();
        });
    });
})();
