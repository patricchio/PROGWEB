/**
 * Inizializza le variabili principali se l'elemento del gioco è presente nella pagina.
 */
var game = document.querySelector('[data-game-code]');

if (game) {
    // Salva lo stato iniziale per verificare futuri cambiamenti
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

    /**
     * Ritorna il tempo attuale in secondi (basato sul client, ma usato in analogia al server).
     */
    function serverNow() { return Date.now() / 1000; }

    /**
     * Chiude il round attuale, inviando la risposta automaticamente se necessario.
     */
    async function closeRound() {
        if (roundClosing) return;
        roundClosing = true;
        timer.textContent = 'CONFERMA...';

        var draftConfirmed = false;
        // Invia la bozza corrente al server se è presente il form di risposta
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

        // Ricarica la pagina se è in modalità single player e la bozza è stata confermata
        if (singlePlayer && draftConfirmed) {
            window.location.reload();
            return;
        }

        // Attende il termine del tempo di tolleranza per poi valutare il turno
        var waitMilliseconds = Math.max(0,
            Math.ceil((deadline + confirmationGraceSeconds - serverNow()) * 1000));
        window.setTimeout(function () {
            timer.textContent = 'VERDETTO...';
            if (autoEvaluate) autoEvaluate.requestSubmit();
        }, waitMilliseconds);
    }

    /**
     * Aggiorna il testo del timer in base al tempo rimanente.
     */
    function updateTimer() {
        if (!timer || !deadline || roundClosing) return;
        var remaining = Math.max(0, Math.ceil(deadline - serverNow()));
        timer.textContent = '00:' + String(remaining).padStart(2, '0');
        timer.classList.toggle('timer-danger', remaining <= 10);

        // Se il tempo è scaduto, avvia la chiusura del round
        if (remaining === 0 && autoEvaluate) {
            closeRound();
        }
    }

    // Inizializza il timer e imposta l'intervallo per l'aggiornamento continuo
    updateTimer();
    window.setInterval(updateTimer, 250);

    // Gestisce il submit manuale del form per evitare invii dopo lo scadere del tempo
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

    /**
     * Polling periodico per verificare se lo stato del gioco è cambiato sul server.
     */
    window.setInterval(async function () {
        if (document.hidden) return;
        try {
            var response = await fetch((document.body.dataset.baseUrl || '') + '/api/game/' + game.dataset.gameCode, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            var state = await response.json();
            // Se la fase, il turno o il numero di giocatori cambia, ricarica la pagina
            if (state.phase !== initial.phase || state.round !== initial.round || state.players.length !== initial.players) {
                window.location.reload();
            }
        } catch (e) {
            // Il polling riprova automaticamente in caso di errore.
        }
    }, 2500);

}
