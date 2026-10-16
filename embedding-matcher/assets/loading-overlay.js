(function () {
    var overlay = document.getElementById('request-loading-overlay');
    var title = document.getElementById('request-loading-title');
    var status = document.getElementById('request-loading-status');
    var card = overlay && overlay.querySelector('.loading-card');
    if (!overlay || !title || !status || !card) return;

    var titles = {
        generate: 'Generating your curriculum draft',
        enhance: 'Enhancing your curriculum'
    };
    var stages = [
        'Course and skill data gathering',
        'Course-to-skill matching',
        'Recommendation drafting',
        'Draft validation',
        'Result preparation'
    ];
    var active = false;
    var stageTimer = null;
    var longWaitTimer = null;
    var previousFocus = null;
    var previousOverflow = '';
    var formStates = [];

    function stopTimers() {
        window.clearInterval(stageTimer);
        window.clearTimeout(longWaitTimer);
        stageTimer = null;
        longWaitTimer = null;
    }

    function reset() {
        stopTimers();
        if (active) {
            active = false;
            overlay.hidden = true;
            document.body.style.overflow = previousOverflow;
            if (previousFocus && document.contains(previousFocus)) previousFocus.focus();
            previousFocus = null;
        }
        formStates.forEach(function (state) {
            state.submitted = false;
            state.form.classList.remove('loading-inline-form');
            state.buttons.forEach(function (button) {
                button.removeAttribute('aria-disabled');
            });
            if (state.inlineStatus) state.inlineStatus.hidden = true;
        });
    }

    function showOverlay(action) {
        title.textContent = titles[action];
        status.classList.remove('is-fading');
        status.textContent = stages[0];
        overlay.hidden = false;
        active = true;
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        card.focus();

        var stageIndex = 0;
        stageTimer = window.setInterval(function () {
            status.classList.add('is-fading');
            window.setTimeout(function () {
                if (!active) return;
                stageIndex = (stageIndex + 1) % stages.length;
                status.textContent = stages[stageIndex];
                status.classList.remove('is-fading');
            }, 180);
        }, 3500);
        longWaitTimer = window.setTimeout(function () {
            window.clearInterval(stageTimer);
            stageTimer = null;
            status.classList.add('is-fading');
            window.setTimeout(function () {
                if (!active) return;
                status.textContent = 'Still working. This is taking longer than usual.';
                status.classList.remove('is-fading');
            }, 180);
        }, 45000);
    }

    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        var action = form.dataset.loading;
        var submitButtons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
        var inlineStatus = form.querySelector('[data-loading-status]');
        var formState = {
            form: form,
            buttons: submitButtons,
            inlineStatus: inlineStatus,
            submitted: false
        };
        formStates.push(formState);

        form.addEventListener('submit', function (event) {
            if (formState.submitted) {
                event.preventDefault();
                return;
            }
            if (submitButtons.some(function (button) { return button.disabled; })) return;
            formState.submitted = true;
            submitButtons.forEach(function (button) {
                button.setAttribute('aria-disabled', 'true');
            });

            if (action === 'chat') {
                form.classList.add('loading-inline-form');
                if (inlineStatus) inlineStatus.hidden = false;
            } else if (titles[action]) {
                showOverlay(action);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!active) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            return;
        }
        if (event.key === 'Tab') {
            event.preventDefault();
            card.focus();
        }
    });

    document.addEventListener('focusin', function (event) {
        if (active && !overlay.contains(event.target)) card.focus();
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) reset();
    });
}());
