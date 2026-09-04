<?php


class FPersistentManager
{
    private $database;

    /**
     * Inizializza il gestore usando la connessione PDO fornita o quella di default.
     */
    public function __construct($database = null)
    {
        $this->database = $database !== null ? $database : FDatabase::connection();
    }

    /**
     * Crea un nuovo utente nel database e ne restituisce l'istanza.
     */
    public function createUser(string $username, string $email, string $passwordHash): EUser
    {
        $statement = $this->database->prepare(
            'INSERT INTO users (username, email, password_hash, is_admin) VALUES (:username, :email, :password_hash, 0)'
        );
        $statement->execute(['username' => $username, 'email' => $email, 'password_hash' => $passwordHash]);

        return new EUser((int) $this->database->lastInsertId(), $username, $email, $passwordHash, false);
    }

    /**
     * Cerca un utente tramite indirizzo email. Restituisce null se non esiste.
     */
    public function findUserByEmail(string $email): ?EUser
    {
        $statement = $this->database->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row === false ? null : EUser::fromRow($row);
    }

    /**
     * Verifica se uno username o un'email sono già utilizzati da un altro utente.
     */
    public function usernameOrEmailExists(string $username, string $email): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $statement->execute(['username' => $username, 'email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Salva una nuova partita (e il suo host come primo game_player) nel database.
     */
    public function createGame(EGame $game): EGame
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO games (code, host_user_id, status, phase, max_players, initial_lives, round_duration_seconds)
                 VALUES (:code, :host, :status, :phase, :max_players, :lives, :round_duration)'
            );
            $statement->execute([
                'code' => $game->code,
                'host' => $game->hostUserId,
                'status' => $game->status,
                'phase' => $game->phase,
                'max_players' => $game->maxPlayers,
                'lives' => $game->initialLives,
                'round_duration' => $game->roundDurationSeconds,
            ]);
            $game->id = (int) $this->database->lastInsertId();

            $insertPlayer = $this->database->prepare(
                'INSERT INTO game_players (game_id, user_id, lives, current_answer)
                 VALUES (:game_id, :user_id, :lives, :answer)'
            );
            foreach ($game->players as &$player) {
                $insertPlayer->execute([
                    'game_id' => $game->id,
                    'user_id' => $player['user_id'],
                    'lives' => $player['lives'],
                    'answer' => $player['answer'],
                ]);
                $player['game_player_id'] = (int) $this->database->lastInsertId();
            }
            unset($player);

            $this->database->commit();
            return $game;
        } catch (Exception $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Cerca una partita in base al suo codice a 6 caratteri, con giocatori e
     * (se disponibile) l'ultimo turno valutato.
     */
    public function findGameByCode(string $code): ?EGame
    {
        return $this->loadGame($code, false);
    }

    /**
     * Verifica se un codice partita esiste già.
     */
    public function gameCodeExists(string $code): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM games WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        return $statement->fetchColumn() !== false;
    }

    /**
     * Recupera le ultime partite a cui l'utente ha partecipato.
     */
    public function findGamesByUser(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT g.*, COUNT(gp2.id) AS player_count
             FROM games g
             JOIN game_players gp ON gp.game_id = g.id AND gp.user_id = :user_id
             LEFT JOIN game_players gp2 ON gp2.game_id = g.id
             GROUP BY g.id
             ORDER BY g.updated_at DESC LIMIT 8'
        );
        $statement->execute(['user_id' => $userId]);
        return array_map(function ($row) { return EGame::fromSummaryRow($row); }, $statement->fetchAll());
    }

    /**
     * Restituisce gli scenari degli ultimi turni giocati, dal più recente,
     * per evitare che l'IA ripeta contenuti già usati.
     */
    public function recentScenarios(int $gameId, int $limit = 5): array
    {
        $statement = $this->database->prepare(
            'SELECT scenario FROM rounds WHERE game_id = :game_id
             ORDER BY round_number DESC LIMIT :limit'
        );
        $statement->bindValue('game_id', $gameId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(function ($row) { return (string) $row['scenario']; }, $statement->fetchAll());
    }

    /**
     * Elimina una partita dal database, solo se l'utente ne è host e se non è già finita.
     * Giocatori, round e risultati collegati vengono rimossi in cascata dalle FK.
     */
    public function deleteUnfinishedGame(string $code, int $hostUserId): bool
    {
        $statement = $this->database->prepare(
            'DELETE FROM games WHERE code = :code AND host_user_id = :host_user_id AND status <> "FINISHED"'
        );
        $statement->execute(['code' => $code, 'host_user_id' => $hostUserId]);
        return $statement->rowCount() === 1;
    }

    /**
     * Recupera tutte le partite attive (o in lobby) per il pannello moderatore.
     */
    public function findAllActiveGames(): array
    {
        $statement = $this->database->prepare(
            'SELECT g.*, COUNT(gp.id) AS player_count
             FROM games g
             LEFT JOIN game_players gp ON gp.game_id = g.id
             WHERE g.status <> "FINISHED"
             GROUP BY g.id
             ORDER BY g.updated_at DESC'
        );
        $statement->execute();
        return array_map(function ($row) { return EGame::fromSummaryRow($row); }, $statement->fetchAll());
    }

    /**
     * Recupera tutti gli utenti per il pannello moderatore.
     */
    public function findAllUsers(): array
    {
        $statement = $this->database->prepare('SELECT * FROM users ORDER BY created_at DESC');
        $statement->execute();
        return array_map(function ($row) { return EUser::fromRow($row); }, $statement->fetchAll());
    }

    /**
     * Elimina fisicamente un utente dal sistema (e tutte le partite che ha creato).
     */
    public function deleteUser(int $userId): bool
    {
        // Rimuoviamo prima le partite ospitate da questo utente per evitare l'errore
        // di Integrity Constraint (ON DELETE RESTRICT su host_user_id).
        // I round, giocatori e risultati verranno eliminati a cascata da MySQL.
        $statementGames = $this->database->prepare('DELETE FROM games WHERE host_user_id = :id');
        $statementGames->execute(['id' => $userId]);

        $statement = $this->database->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        return $statement->rowCount() === 1;
    }

    /**
     * Modifica lo stato di una partita in modo sicuro (transazione atomica e blocco di riga).
     */
    public function mutateGame(string $code, callable $mutation): EGame
    {
        $this->database->beginTransaction();
        try {
            $game = $this->loadGame($code, true);
            if ($game === null) {
                throw new DomainException('Partita non trovata.');
            }
            $mutation($game);
            $this->saveGame($game);
            $this->database->commit();
            return $game;
        } catch (Exception $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Carica una partita completa (riga games, giocatori e, se in fase RESULTS
     * o FINISHED, l'ultimo turno valutato). Con $lock true, blocca le righe
     * coinvolte con FOR UPDATE per l'uso dentro una transazione.
     */
    private function loadGame(string $code, bool $lock): ?EGame
    {
        $suffix = '';

        $statement = $this->database->prepare("SELECT * FROM games WHERE code = :code$suffix");
        $statement->execute(['code' => $code]);
        $gameRow = $statement->fetch();
        if ($gameRow === false) {
            return null;
        }

        $playersStatement = $this->database->prepare(
            "SELECT gp.id AS game_player_id, gp.user_id, u.username, gp.lives, gp.current_answer
             FROM game_players gp JOIN users u ON u.id = gp.user_id
             WHERE gp.game_id = :game_id ORDER BY gp.id ASC$suffix"
        );
        $playersStatement->execute(['game_id' => $gameRow['id']]);
        $players = [];
        foreach ($playersStatement->fetchAll() as $playerRow) {
            $players[(int) $playerRow['user_id']] = [
                'game_player_id' => (int) $playerRow['game_player_id'],
                'user_id' => (int) $playerRow['user_id'],
                'username' => (string) $playerRow['username'],
                'lives' => (int) $playerRow['lives'],
                'answer' => $playerRow['current_answer'],
            ];
        }

        $lastResults = [];
        $judgmentSource = 'fallback';
        $storySource = 'fallback';
        if (in_array($gameRow['phase'], ['RESULTS', 'FINISHED'], true)) {
            $roundStatement = $this->database->prepare(
                'SELECT id, judgment_source, story_source FROM rounds
                 WHERE game_id = :game_id ORDER BY round_number DESC LIMIT 1'
            );
            $roundStatement->execute(['game_id' => $gameRow['id']]);
            $roundRow = $roundStatement->fetch();
            if ($roundRow !== false) {
                $judgmentSource = (string) $roundRow['judgment_source'];
                $storySource = (string) $roundRow['story_source'];
                $resultsStatement = $this->database->prepare(
                    'SELECT rr.answer, rr.outcome, rr.story, rr.lives_after, gp.user_id, u.username
                     FROM round_results rr
                     JOIN game_players gp ON gp.id = rr.game_player_id
                     JOIN users u ON u.id = gp.user_id
                     WHERE rr.round_id = :round_id ORDER BY rr.id ASC'
                );
                $resultsStatement->execute(['round_id' => $roundRow['id']]);
                foreach ($resultsStatement->fetchAll() as $resultRow) {
                    $lastResults[] = [
                        'player_id' => (int) $resultRow['user_id'],
                        'username' => (string) $resultRow['username'],
                        'outcome' => (string) $resultRow['outcome'],
                        'answer' => (string) $resultRow['answer'],
                        'story' => (string) $resultRow['story'],
                        'lives' => (int) $resultRow['lives_after'],
                    ];
                }
            }
        }

        return EGame::fromRow($gameRow, $players, $lastResults, $judgmentSource, $storySource);
    }

    /**
     * Persiste una partita già caricata: riga games, giocatori modificati o
     * nuovi e, se presente, il turno appena valutato da applyResults().
     */
    private function saveGame(EGame $game): void
    {
        $update = $this->database->prepare(
            'UPDATE games SET status = :status, phase = :phase, round = :round,
             rounds_played = :rounds_played, scenario = :scenario, deadline_at = :deadline_at,
             winner_user_id = :winner_user_id,
             finished_at = IF(:finished_status = "FINISHED", COALESCE(finished_at, NOW()), finished_at)
             WHERE id = :id'
        );
        $update->execute([
            'status' => $game->status,
            'phase' => $game->phase,
            'round' => $game->round,
            'rounds_played' => $game->roundsPlayed,
            'scenario' => $game->scenario,
            'deadline_at' => $game->deadlineAt,
            'winner_user_id' => $game->winnerUserId,
            'finished_status' => $game->status,
            'id' => $game->id,
        ]);

        $insertPlayer = $this->database->prepare(
            'INSERT INTO game_players (game_id, user_id, lives, current_answer)
             VALUES (:game_id, :user_id, :lives, :answer)'
        );
        $updatePlayer = $this->database->prepare(
            'UPDATE game_players SET lives = :lives, current_answer = :answer WHERE id = :id'
        );
        foreach ($game->players as &$player) {
            if ($player['game_player_id'] === 0) {
                $insertPlayer->execute([
                    'game_id' => $game->id,
                    'user_id' => $player['user_id'],
                    'lives' => $player['lives'],
                    'answer' => $player['answer'],
                ]);
                $player['game_player_id'] = (int) $this->database->lastInsertId();
            } else {
                $updatePlayer->execute([
                    'lives' => $player['lives'],
                    'answer' => $player['answer'],
                    'id' => $player['game_player_id'],
                ]);
            }
        }
        unset($player);

        if ($game->pendingRound !== null) {
            $round = $game->pendingRound;
            $insertRound = $this->database->prepare(
                'INSERT INTO rounds (game_id, round_number, scenario, judgment_source, story_source)
                 VALUES (:game_id, :round_number, :scenario, :judgment_source, :story_source)'
            );
            $insertRound->execute([
                'game_id' => $game->id,
                'round_number' => $round['round_number'],
                'scenario' => $round['scenario'],
                'judgment_source' => $round['judgment_source'],
                'story_source' => $round['story_source'],
            ]);
            $roundId = (int) $this->database->lastInsertId();

            $insertResult = $this->database->prepare(
                'INSERT INTO round_results (round_id, game_player_id, answer, outcome, story, lives_after)
                 VALUES (:round_id, :game_player_id, :answer, :outcome, :story, :lives_after)'
            );
            foreach ($round['results'] as $result) {
                $insertResult->execute([
                    'round_id' => $roundId,
                    'game_player_id' => $game->players[$result['user_id']]['game_player_id'],
                    'answer' => $result['answer'],
                    'outcome' => $result['outcome'],
                    'story' => $result['story'],
                    'lives_after' => $result['lives'],
                ]);
            }
            $game->pendingRound = null;
        }
    }
}
