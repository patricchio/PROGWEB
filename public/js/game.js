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
    const deadline = Number(game.dataset.deadlineAt || 0);
    const renderedServerTime = Number(game.dataset.serverTime || 0);
    const clockOffset = renderedServerTime - (Date.now() / 1000);
    let evaluationSubmitted = false;

    const updateTimer = () => {
        if (!timer || !deadline) return;
        const serverNow = (Date.now() / 1000) + clockOffset;
        const remaining = Math.max(0, Math.ceil(deadline - serverNow));
        timer.textContent = `00:${String(remaining).padStart(2, '0')}`;
        timer.classList.toggle('timer-danger', remaining <= 10);

        if (remaining === 0 && autoEvaluate && !evaluationSubmitted) {
            evaluationSubmitted = true;
            timer.textContent = 'VERDETTO...';
            autoEvaluate.requestSubmit();
        }
    };

    updateTimer();
    window.setInterval(updateTimer, 250);

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
