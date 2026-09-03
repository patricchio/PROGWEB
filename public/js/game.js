var game = document.querySelector('[data-game-code]');

if (game) {
    var initial = {
        phase: game.dataset.gamePhase,
        round: Number(game.dataset.gameRound),
        players: document.querySelectorAll('.player-list > li').length,
    };
    var timer = game.querySelector('[data-round-timer]');
    var autoEvaluate = game.querySelector('[data-auto-evaluate]');
    var answerForm = game.querySelector('[data-answer-form]');
    var singlePlayer = game.dataset.singlePlayer === '1';
    var deadline = Number(game.dataset.deadlineAt || 0);
    var confirmationGraceSeconds = Number(game.dataset.confirmationGrace || 0);
    var roundClosing = false;

    function serverNow() { return Date.now() / 1000; }

    async function closeRound() {
        if (roundClosing) return;
        roundClosing = true;
        timer.textContent = 'CONFERMA...';

        var draftConfirmed = false;
        if (answerForm) {
            var answerField = answerForm.elements.answer;
            var answer = answerField.value.trim();
            var button = answerForm.querySelector('button[type="submit"]');
            answerField.readOnly = true;
            if (button) button.disabled = true;
            if (answer !== '') {
                var formData = new FormData(answerForm);
                formData.set('answer', answer);
                formData.set('automatic', '1');
                try {
                    var response = await fetch(answerForm.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });
                    draftConfirmed = response.ok;
                } catch (e) {
                    // La valutazione gestirà comunque l'assenza di una risposta.
                }
            }
        }

        if (singlePlayer && draftConfirmed) {
            window.location.reload();
            return;
        }

        var waitMilliseconds = Math.max(0,
            Math.ceil((deadline + confirmationGraceSeconds - serverNow()) * 1000));
        window.setTimeout(function () {
            timer.textContent = 'VERDETTO...';
            if (autoEvaluate) autoEvaluate.requestSubmit();
        }, waitMilliseconds);
    }

    function updateTimer() {
        if (!timer || !deadline || roundClosing) return;
        var remaining = Math.max(0, Math.ceil(deadline - serverNow()));
        timer.textContent = '00:' + String(remaining).padStart(2, '0');
        timer.classList.toggle('timer-danger', remaining <= 10);

        if (remaining === 0 && autoEvaluate) {
            closeRound();
        }
    }

    updateTimer();
    window.setInterval(updateTimer, 250);

    if (answerForm) {
        answerForm.addEventListener('submit', function (event) {
            if (deadline && serverNow() >= deadline) {
                event.preventDefault();
                closeRound();
                return;
            }
            var button = answerForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = singlePlayer ? 'VALUTAZIONE...' : 'CONFERMA...';
            }
        });
    }

    window.setInterval(async function () {
        if (document.hidden) return;
        try {
            var response = await fetch((document.body.dataset.baseUrl || '') + '/api/game/' + game.dataset.gameCode, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            var state = await response.json();
            if (state.phase !== initial.phase || state.round !== initial.round || state.players.length !== initial.players) {
                window.location.reload();
            }
        } catch (e) {
            // Il polling riprova automaticamente.
        }
    }, 2500);

}
