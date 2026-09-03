<?php

declare(strict_types=1);

final class FPersistentManager
{
    /**
     * Inizializza il gestore usando la connessione PDO fornita o quella di default.
     */
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= FDatabase::connection();
    }

    /**
     * Crea un nuovo utente nel database e ne restituisce l'istanza.
     */
    public function createUser(string $username, string $email, string $passwordHash): EUser
    {
        $statement = $this->database->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)'
        );
        $statement->execute(['username' => $username, 'email' => $email, 'password_hash' => $passwordHash]);

        return new EUser((int) $this->database->lastInsertId(), $username, $email, $passwordHash);
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
     * Salva una nuova partita nel database e le assegna un ID.
     */
    public function createGame(EGame $game): EGame
    {
        $statement = $this->database->prepare(
            'INSERT INTO games (code, host_user_id, status, max_players, initial_lives, scenario_type, scenario_value, state_json)
             VALUES (:code, :host, :status, :max_players, :lives, :scenario_type, :scenario_value, :state_json)'
        );
        $statement->execute([
            'code' => $game->code,
            'host' => $game->hostUserId,
            'status' => $game->status,
            'max_players' => $game->maxPlayers,
            'lives' => $game->initialLives,
            // Colonne mantenute soltanto per compatibilità con il database esistente.
            'scenario_type' => 'RANDOM',
            'scenario_value' => null,
            'state_json' => json_encode($game->state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        $game->id = (int) $this->database->lastInsertId();
        return $game;
    }

    /**
     * Cerca una partita in base al suo codice a 6 caratteri.
     */
    public function findGameByCode(string $code): ?EGame
    {
        $statement = $this->database->prepare('SELECT * FROM games WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();
        return $row === false ? null : EGame::fromRow($row);
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
     * Recupera le ultime partite in cui l'utente specificato è host.
     */
    public function findGamesHostedByUser(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM games WHERE host_user_id = :user_id ORDER BY updated_at DESC LIMIT 8'
        );
        $statement->execute(['user_id' => $userId]);
        return array_map(static fn (array $row): EGame => EGame::fromRow($row), $statement->fetchAll());
    }

    /**
     * Elimina una partita dal database, solo se l'utente ne è host e se non è già finita.
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
     * Modifica lo stato di una partita in modo sicuro (transazione atomica e blocco di riga).
     */
    public function mutateGame(string $code, callable $mutation): EGame
    {
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare('SELECT * FROM games WHERE code = :code FOR UPDATE');
            $statement->execute(['code' => $code]);
            $row = $statement->fetch();
            if ($row === false) {
                throw new DomainException('Partita non trovata.');
            }
            $game = EGame::fromRow($row);
            $mutation($game);
            $update = $this->database->prepare(
                'UPDATE games SET status = :status, state_json = :state_json,
                 finished_at = IF(:finished_status = "FINISHED", COALESCE(finished_at, NOW()), finished_at)
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $game->status,
                'finished_status' => $game->status,
                'state_json' => json_encode($game->state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'id' => $game->id,
            ]);
            $this->database->commit();
            return $game;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
