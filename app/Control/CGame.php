<?php

class CGame
{
    private const AUTOMATIC_CONFIRMATION_GRACE_SECONDS = 2;

    /**
     * Costruttore: riceve la vista e l'URL base.
     */
    private $view;
    private $baseUrl;

    public function __construct($view, $baseUrl)
    {
        $this->view = $view;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Mostra la pagina principale o la dashboard se l'utente è loggato.
     */
    public function home(): void
    {
        $user = FSession::user();
        $template = $user === null ? 'home.tpl' : 'dashboard.tpl';
        $games = [];
        if ($user !== null) {
            try {
                $games = (new FPersistentManager())->findGamesByUser((int) $user['id']);
            } catch (Exception) {
                // La pagina resta accessibile e mostrerà l'errore solo al primo salvataggio.
            }
        }
        $this->view->render($template, [
            'page_title' => 'Death by AI - Sopravvivi alla storia',
            'base_url' => $this->baseUrl,
            'recent_games' => $games,
        ]);
    }

    /**
     * Crea una nuova partita leggendo i parametri dal form.
     */
    public function create(): void
    {
        $user = FSession::requireUser($this->baseUrl);
        $maxPlayers = (int) ($_POST['max_players'] ?? 1);
        $lives = (int) ($_POST['lives'] ?? 2);
        $roundDuration = (int) ($_POST['round_duration'] ?? 30);

        if ($maxPlayers < 1 || $maxPlayers > 5 || $lives < 1 || $lives > 3
            || $roundDuration < 10 || $roundDuration > 60) {
            FSession::flash('error', 'Configurazione della partita non valida.');
            $this->redirect('/');
        }

        try {
            $manager = new FPersistentManager();
            do {
                $code = $this->generateGameCode();
            } while ($manager->gameCodeExists($code));
            $manager->createGame(EGame::create(
                $code,
                $user,
                $maxPlayers,
                $lives,
                $roundDuration
            ));
            $this->redirect('/game/' . $code);
        } catch (Exception $exception) {
            FSession::flash('error', 'Impossibile creare la partita: ' . $exception->getMessage());
            $this->redirect('/');
        }
    }

    /**
     * Fa entrare un utente in una partita tramite il codice inserito.
     */
    public function join(): void
    {
        $user = FSession::requireUser($this->baseUrl);
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($_POST['code'] ?? ''))) ?? '';
        if (preg_match('/^[A-Z0-9]{6}$/', $code) !== 1) {
            FSession::flash('error', 'Il codice deve contenere 6 caratteri.');
            $this->redirect('/');
        }
        try {
            (new FPersistentManager())->mutateGame($code, function ($game) use ($user) { $game->addPlayer($user); });
            $this->redirect('/game/' . $code);
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
            $this->redirect('/');
        }
    }

    /**
     * Mostra l'interfaccia di una specifica partita.
     */
    public function show(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        try {
            $game = (new FPersistentManager())->findGameByCode(strtoupper($code));
            if ($game === null || !$game->hasPlayer((int) $user['id'])) {
                throw new DomainException('Non partecipi a questa partita.');
            }
            $currentPlayer = $game->players[(int) $user['id']] ?? null;
            $this->view->render('game.tpl', [
                'page_title' => ($game->maxPlayers === 1 ? 'Single player' : 'Partita ' . $game->code) . ' - Death by AI',
                'base_url' => $this->baseUrl,
                'game' => $game,
                'is_host' => $game->hostUserId === (int) $user['id'],
                'current_player' => $currentPlayer,
                'server_time' => time(),
                'automatic_confirmation_grace' => self::AUTOMATIC_CONFIRMATION_GRACE_SECONDS,
            ]);
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
            $this->redirect('/');
        }
    }

    /**
     * L'host avvia la partita e genera il primo scenario.
     */
    public function start(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        try {
            $manager = new FPersistentManager();
            $game = $this->requireHost($manager, $code, (int) $user['id']);
            $previousScenarios = $manager->recentScenarios($game->id);
            $scenario = (new FAIService())->createScenario($previousScenarios, $game->roundNumber + 1);
            $manager->mutateGame($game->code, function ($lockedGame) use ($user, $scenario) {
                if ($lockedGame->hostUserId !== (int) $user['id']) {
                    throw new DomainException('Solo l’host può iniziare.');
                }
                $lockedGame->start($scenario);
            });
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
        }
        $this->redirect('/game/' . strtoupper($code));
    }

    /**
     * Salva la risposta di un utente per il turno corrente.
     */
    public function answer(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        $automatic = ($_POST['automatic'] ?? '') === '1';
        $answer = trim((string) ($_POST['answer'] ?? ''));
        $minimumLength = $automatic ? 1 : 3;
        if (mb_strlen($answer) < $minimumLength || mb_strlen($answer) > 700) {
            if ($automatic) {
                $this->view->json(['error' => 'Non c’era alcuna risposta da confermare automaticamente.'], 422);
                return;
            }
            FSession::flash('error', 'La risposta deve contenere da 3 a 700 caratteri.');
            $this->redirect('/game/' . strtoupper($code));
        }
        try {
            $manager = new FPersistentManager();
            $game = $manager->mutateGame(strtoupper($code),
                function ($game) use ($user, $answer, $automatic) { $game->submitAnswer((int) $user['id'], $answer, $automatic); });
            if ($game->maxPlayers === 1) {
                $this->runEvaluation($manager, $game->code, (int) $user['id'], true);
            }
            if ($automatic) {
                $this->view->json(['confirmed' => true]);
                return;
            }
            // redirect senza messaggio, la risposta è visibile nella pagina
        } catch (Exception $exception) {
            if ($automatic) {
                $this->view->json(['error' => $exception->getMessage()], 409);
                return;
            }
            FSession::flash('error', $exception->getMessage());
        }
        $this->redirect('/game/' . strtoupper($code));
    }

    /**
     * Valuta le risposte di tutti i giocatori al termine del turno.
     */
    public function evaluate(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        try {
            $manager = new FPersistentManager();
            $this->runEvaluation($manager, strtoupper($code), (int) $user['id']);
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
        }
        $this->redirect('/game/' . strtoupper($code));
    }

    /**
     * Passa al round successivo generando un nuovo scenario.
     */
    public function nextRound(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        try {
            $manager = new FPersistentManager();
            $game = $this->requireHost($manager, $code, (int) $user['id']);
            $previousScenarios = $manager->recentScenarios($game->id);
            $scenario = (new FAIService())->createScenario($previousScenarios, $game->roundNumber + 1);
            $manager->mutateGame($game->code, function ($lockedGame) use ($user, $scenario) {
                if ($lockedGame->hostUserId !== (int) $user['id']) {
                    throw new DomainException('Solo l’host può continuare.');
                }
                $lockedGame->nextRound($scenario);
            });
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
        }
        $this->redirect('/game/' . strtoupper($code));
    }

    /**
     * Elimina la partita (solo per l'host, se non è terminata).
     */
    public function delete(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        try {
            $deleted = (new FPersistentManager())->deleteUnfinishedGame(strtoupper($code), (int) $user['id']);
            FSession::flash($deleted ? 'success' : 'error',
                $deleted ? 'Partita eliminata.' : 'Puoi eliminare soltanto le tue partite non concluse.');
        } catch (Exception $exception) {
            FSession::flash('error', $exception->getMessage());
        }
        $this->redirect('/');
    }

    /**
     * Endpoint API (AJAX) per ottenere lo stato corrente della partita.
     */
    public function state(string $code): void
    {
        $user = FSession::requireUser($this->baseUrl);
        $game = (new FPersistentManager())->findGameByCode(strtoupper($code));
        if ($game === null || !$game->hasPlayer((int) $user['id'])) {
            $this->view->json(['error' => 'Partita non disponibile'], 404);
            return;
        }
        $players = array_map(function ($player) { return [
            'id' => $player['user_id'], 'username' => $player['username'], 'lives' => $player['lives'],
            'answered' => trim((string) ($player['answer'] ?? '')) !== '',
        ]; }, array_values($game->players));
        $this->view->json(['status' => $game->status, 'round_status' => $game->roundStatus,
            'round_number' => $game->roundNumber, 'players' => $players,
            'deadline_at' => $game->deadlineAt, 'server_time' => time()]);
    }

    /**
     * Controlla che l'utente loggato sia effettivamente l'host della partita.
     */
    private function requireHost(FPersistentManager $manager, string $code, int $userId): EGame
    {
        $game = $manager->findGameByCode(strtoupper($code));
        if ($game === null || $game->hostUserId !== $userId) {
            throw new DomainException('Operazione riservata all’host.');
        }
        return $game;
    }

    /**
     * Gestisce la logica centrale di valutazione chiamando l'IA.
     */
    private function runEvaluation(FPersistentManager $manager, string $code, int $userId,
        bool $allowBeforeDeadline = false): void
    {
        $game = $manager->findGameByCode(strtoupper($code));
        if ($game === null || !$game->hasPlayer($userId)) {
            throw new DomainException('Non partecipi a questa partita.');
        }
        if (!$allowBeforeDeadline && !$this->automaticConfirmationWindowClosed($game)) {
            throw new DomainException('Il timer non è ancora scaduto.');
        }

        $claimedGame = $manager->mutateGame($game->code, function ($lockedGame) use ($userId, $allowBeforeDeadline) {
            if (!$lockedGame->hasPlayer($userId)
                || (!$allowBeforeDeadline && !$this->automaticConfirmationWindowClosed($lockedGame))) {
                throw new DomainException('Il turno non può ancora essere valutato.');
            }
            $lockedGame->prepareEvaluation();
        });
        $ai = new FAIService();
        $evaluation = $ai->evaluateSurvival($claimedGame);
        $judgment = $ai->generateStory($claimedGame, $evaluation);
        $manager->mutateGame($game->code, function ($lockedGame) use ($judgment) {
            $lockedGame->applyResults($judgment['results']);
        });
    }

    /**
     * Controlla se è trascorso il periodo di tolleranza per le risposte automatiche.
     */
    private function automaticConfirmationWindowClosed(EGame $game): bool
    {
        return $game->isDeadlineExpired(time() - self::AUTOMATIC_CONFIRMATION_GRACE_SECONDS);
    }

    /**
     * Genera un codice casuale di 6 caratteri alfanumerici per le nuove partite.
     */
    private function generateGameCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($index = 0; $index < 6; $index++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }

    /**
     * Reindirizza l'utente a un percorso specificato e termina lo script.
     */
    private function redirect($path)
    {
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
