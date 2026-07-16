(function () {
    'use strict';

    var viewport = document.getElementById('tp-viewport');
    var toggleButton = document.getElementById('tp-toggle');
    var speedInput = document.getElementById('tp-speed');
    var fontSizeInput = document.getElementById('tp-fontsize');
    var text = document.getElementById('tp-text');

    if (!viewport || !toggleButton || !speedInput || !fontSizeInput) {
        return;
    }

    var playing = false;
    var lastTimestamp = null;

    function setPlaying(next) {
        playing = next;
        toggleButton.textContent = playing ? 'Pause' : 'Play';
        if (playing) {
            lastTimestamp = null;
            window.requestAnimationFrame(tick);
        }
    }

    function tick(timestamp) {
        if (!playing) {
            return;
        }
        if (lastTimestamp !== null) {
            var deltaSeconds = (timestamp - lastTimestamp) / 1000;
            var pixelsPerSecond = Number(speedInput.value);
            viewport.scrollTop += pixelsPerSecond * deltaSeconds;

            if (viewport.scrollTop >= viewport.scrollHeight - viewport.clientHeight) {
                setPlaying(false);
                lastTimestamp = timestamp;
                return;
            }
        }
        lastTimestamp = timestamp;
        window.requestAnimationFrame(tick);
    }

    toggleButton.addEventListener('click', function () {
        setPlaying(!playing);
    });

    if (text) {
        fontSizeInput.addEventListener('input', function () {
            text.style.fontSize = fontSizeInput.value + 'px';
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.code === 'Space' && event.target === document.body) {
            event.preventDefault();
            setPlaying(!playing);
        }
    });
})();
