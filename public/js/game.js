(() => {
    const game = document.querySelector('[data-game-code]');
    if (!game) return;

    const initial = {
        phase: game.dataset.gamePhase,
        round: Number(game.dataset.gameRound),
        players: document.querySelectorAll('.player-list > li').length,
    };
    const timer = game.querySelector('[data-round-timer]');
    const autoEvaluate = game.querySelector('[data-auto-evaluate]');
    const answerForm = game.querySelector('[data-answer-form]');
    const singlePlayer = game.dataset.singlePlayer === '1';
    const deadline = Number(game.dataset.deadlineAt || 0);
    const renderedServerTime = Number(game.dataset.serverTime || 0);
    const clockOffset = renderedServerTime - (Date.now() / 1000);
    const confirmationGraceSeconds = Number(game.dataset.confirmationGrace || 0);
    let roundClosing = false;

    const serverNow = () => (Date.now() / 1000) + clockOffset;

    const closeRound = async () => {
        if (roundClosing) return;
        roundClosing = true;
        timer.textContent = 'CONFERMA...';

        let draftConfirmed = false;
        if (answerForm) {
            const answerField = answerForm.elements.answer;
            const answer = answerField.value.trim();
            const button = answerForm.querySelector('button[type="submit"]');
            answerField.readOnly = true;
            if (button) button.disabled = true;
            if (answer !== '') {
                const formData = new FormData(answerForm);
                formData.set('answer', answer);
                formData.set('automatic', '1');
                try {
                    const response = await fetch(answerForm.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });
                    draftConfirmed = response.ok;
                } catch (_) {
                    // La valutazione gestirà comunque l'assenza di una risposta.
                }
            }
        }

        if (singlePlayer && draftConfirmed) {
            window.location.reload();
            return;
        }

        const waitMilliseconds = Math.max(0,
            Math.ceil((deadline + confirmationGraceSeconds - serverNow()) * 1000));
        window.setTimeout(() => {
            timer.textContent = 'VERDETTO...';
            autoEvaluate?.requestSubmit();
        }, waitMilliseconds);
    };

    const updateTimer = () => {
        if (!timer || !deadline || roundClosing) return;
        const remaining = Math.max(0, Math.ceil(deadline - serverNow()));
        timer.textContent = `00:${String(remaining).padStart(2, '0')}`;
        timer.classList.toggle('timer-danger', remaining <= 10);

        if (remaining === 0 && autoEvaluate) {
            void closeRound();
        }
    };

    updateTimer();
    window.setInterval(updateTimer, 250);

    answerForm?.addEventListener('submit', (event) => {
        if (deadline && serverNow() >= deadline) {
            event.preventDefault();
            void closeRound();
            return;
        }
        const button = answerForm.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = singlePlayer ? 'VALUTAZIONE...' : 'CONFERMA...';
        }
    });

    window.setInterval(async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(`${document.body.dataset.baseUrl || ''}/api/game/${game.dataset.gameCode}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const state = await response.json();
            if (state.phase !== initial.phase || state.round !== initial.round || state.players.length !== initial.players) {
                window.location.reload();
            }
        } catch (_) {
            // Il polling riprova automaticamente.
        }
    }, 2500);

    const speakButton = document.querySelector('[data-speak-verdict]');
    if (speakButton && 'speechSynthesis' in window) {
        speakButton.addEventListener('click', () => {
            window.speechSynthesis.cancel();
            const stories = [...document.querySelectorAll('[data-verdict-text] li p')]
                .map((item) => item.innerText)
                .join('. ');
            const speech = new SpeechSynthesisUtterance(stories);
            speech.lang = 'it-IT';
            speech.rate = 0.95;
            window.speechSynthesis.speak(speech);
        });
    } else if (speakButton) {
        speakButton.hidden = true;
    }
})();
