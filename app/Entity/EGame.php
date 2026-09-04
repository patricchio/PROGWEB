<?php

/**
 * Entità principale della partita. Lo stato del turno corrente viene caricato
 * dalla tabella rounds e non è duplicato nella riga games.
 */
class EGame
{
    public $id;
    public $code;
    public $hostUserId;
    public $status;
    public $maxPlayers;
    public $initialLives;
    public $roundDurationSeconds;
    public $roundsPlayed;
    public $winnerUserId;
    public $winnerUsername;
    public $players;
    public $lastResults;
    public $playerCount;

    public $currentRoundId;
    public $roundNumber;
    public $roundStatus;
    public $scenario;
    public $deadlineAt;
    public $roundCompletedAt;

    public function __construct(
        $id,
        $code,
        $hostUserId,
        $status,
        $maxPlayers,
        $initialLives,
        $roundDurationSeconds,
        $roundsPlayed,
        $winnerUserId,
        $winnerUsername,
        $players,
        $lastResults,
        $currentRoundId = null,
        $roundNumber = 0,
        $roundStatus = null,
        $scenario = null,
        $deadlineAt = null,
        $roundCompletedAt = null,
        $playerCount = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->hostUserId = $hostUserId;
        $this->status = $status;
        $this->maxPlayers = $maxPlayers;
        $this->initialLives = $initialLives;
        $this->roundDurationSeconds = $roundDurationSeconds;
        $this->roundsPlayed = $roundsPlayed;
        $this->winnerUserId = $winnerUserId;
        $this->winnerUsername = $winnerUsername;
        $this->players = $players;
        $this->lastResults = $lastResults;
        $this->currentRoundId = $currentRoundId;
        $this->roundNumber = $roundNumber;
        $this->roundStatus = $roundStatus;
        $this->scenario = $scenario;
        $this->deadlineAt = $deadlineAt;
        $this->roundCompletedAt = $roundCompletedAt;
        $this->playerCount = $playerCount;
    }

    /** Crea una nuova partita ancora priva di round. */
    public static function create(
        string $code,
        array $host,
        int $maxPlayers,
        int $lives,
        int $roundDuration = 30
    ): self {
        $hostId = (int) $host['id'];
        return new self(
            0,
            $code,
            $hostId,
            'LOBBY',
            $maxPlayers,
            $lives,
            $roundDuration,
            0,
            null,
            null,
            [$hostId => [
                'game_player_id' => 0,
                'user_id' => $hostId,
                'username' => (string) $host['username'],
                'lives' => $lives,
                'answer' => null,
            ]],
            []
        );
    }

    /** Ricostruisce una partita e il suo ultimo round. */
    public static function fromRow(
        array $row,
        array $players,
        array $lastResults,
        ?array $roundRow = null
    ): self {
        $winnerUserId = $row['winner_user_id'] !== null ? (int) $row['winner_user_id'] : null;
        $winnerUsername = $winnerUserId !== null ? ($players[$winnerUserId]['username'] ?? null) : null;

        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['host_user_id'],
            (string) $row['status'],
            (int) $row['max_players'],
            (int) $row['initial_lives'],
            (int) $row['round_duration_seconds'],
            (int) $row['rounds_played'],
            $winnerUserId,
            $winnerUsername,
            $players,
            $lastResults,
            $roundRow !== null ? (int) $roundRow['id'] : null,
            $roundRow !== null ? (int) $roundRow['round_number'] : 0,
            $roundRow !== null ? (string) $roundRow['status'] : null,
            $roundRow !== null ? (string) $roundRow['scenario'] : null,
            $roundRow !== null ? (int) $roundRow['deadline_at'] : null,
            $roundRow !== null ? $roundRow['completed_at'] : null
        );
    }

    /** Ricostruisce la versione leggera usata negli elenchi. */
    public static function fromSummaryRow(array $row): self
    {
        $game = self::fromRow($row, [], []);
        $game->playerCount = (int) $row['player_count'];
        return $game;
    }

    /** Aggiunge un partecipante finché la partita è in lobby. */
    public function addPlayer(array $user): void
    {
        if ($this->status !== 'LOBBY') {
            throw new DomainException('La partita è già iniziata.');
        }
        $userId = (int) $user['id'];
        if (isset($this->players[$userId])) {
            return;
        }
        if (count($this->players) >= $this->maxPlayers) {
            throw new DomainException('La lobby è piena.');
        }
        $this->players[$userId] = [
            'game_player_id' => 0,
            'user_id' => $userId,
            'username' => (string) $user['username'],
            'lives' => $this->initialLives,
            'answer' => null,
        ];
    }

    /** Avvia la partita creando in memoria il primo round aperto. */
    public function start(string $scenario): void
    {
        if ($this->status !== 'LOBBY') {
            throw new DomainException('La partita è già iniziata.');
        }
        $this->status = 'ACTIVE';
        $this->openRound(1, $scenario);
    }

    /** Registra la risposta del giocatore per il round aperto. */
    public function submitAnswer(int $userId, string $answer, bool $automatic = false): void
    {
        if ($this->status !== 'ACTIVE' || $this->roundStatus !== 'OPEN') {
            throw new DomainException('Il turno non accetta risposte.');
        }
        if (!isset($this->players[$userId]) || $this->players[$userId]['lives'] <= 0) {
            throw new DomainException('Non puoi rispondere in questa partita.');
        }
        if (trim((string) ($this->players[$userId]['answer'] ?? '')) !== '') {
            throw new DomainException('La risposta è già stata confermata e non può essere modificata.');
        }
        if ($this->isDeadlineExpired() && !$automatic) {
            throw new DomainException('Il tempo è scaduto: la risposta non può più essere confermata manualmente.');
        }
        $this->players[$userId]['answer'] = $answer;
    }

    /** Chiude la raccolta delle risposte e prepara la valutazione. */
    public function prepareEvaluation(): void
    {
        if ($this->status !== 'ACTIVE' || $this->roundStatus !== 'OPEN') {
            return;
        }
        foreach ($this->players as &$player) {
            if ($player['lives'] > 0 && trim((string) ($player['answer'] ?? '')) === '') {
                $player['answer'] = '[NESSUNA RISPOSTA ENTRO IL TEMPO]';
            }
        }
        unset($player);
        $this->roundStatus = 'EVALUATING';
    }

    /** Applica i risultati, aggiorna le vite e completa il round. */
    public function applyResults(array $results): void
    {
        if ($this->status !== 'ACTIVE' || $this->roundStatus !== 'EVALUATING') {
            throw new DomainException('Il turno non può essere valutato.');
        }

        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[(int) $result['player_id']] = $result;
        }

        $this->lastResults = [];
        foreach ($this->players as $userId => &$player) {
            if ($player['lives'] <= 0) {
                continue;
            }
            $result = $resultMap[$userId] ?? null;
            if ($result === null) {
                throw new DomainException('La valutazione AI è incompleta.');
            }
            if (substr((string) $player['answer'], 0, 17) === '[NESSUNA RISPOSTA') {
                $result['outcome'] = 'LOSE_LIFE';
            }
            if ($result['outcome'] === 'LOSE_LIFE') {
                $player['lives'] = max(0, (int) $player['lives'] - 1);
            }
            $this->lastResults[] = [
                'player_id' => $userId,
                'username' => (string) $player['username'],
                'outcome' => (string) $result['outcome'],
                'answer' => (string) $player['answer'],
                'story' => (string) ($result['story'] ?? ''),
                'lives' => (int) $player['lives'],
            ];
        }
        unset($player);

        $this->roundStatus = 'COMPLETED';
        $this->roundCompletedAt = date('Y-m-d H:i:s');
        $this->roundsPlayed++;

        $alive = array_values(array_filter(
            $this->players,
            function ($player) { return (int) $player['lives'] > 0; }
        ));
        $finished = count($this->players) === 1 ? count($alive) === 0 : count($alive) <= 1;
        if ($finished) {
            $this->status = 'FINISHED';
            $this->winnerUserId = count($alive) === 1 ? (int) $alive[0]['user_id'] : null;
            $this->winnerUsername = count($alive) === 1 ? (string) $alive[0]['username'] : null;
        }
    }

    /** Apre il round successivo dopo la visualizzazione dei risultati. */
    public function nextRound(string $scenario): void
    {
        if ($this->status !== 'ACTIVE' || $this->roundStatus !== 'COMPLETED') {
            throw new DomainException('Non è possibile aprire un nuovo turno.');
        }
        foreach ($this->players as &$player) {
            $player['answer'] = null;
        }
        unset($player);
        $this->lastResults = [];
        $this->openRound($this->roundNumber + 1, $scenario);
    }

    /** Termina la partita e annulla l'eventuale round non completato. */
    public function terminate(): void
    {
        if ($this->status === 'FINISHED') {
            throw new DomainException('La partita è già terminata.');
        }
        if (in_array($this->roundStatus, ['OPEN', 'EVALUATING'], true)) {
            $this->roundStatus = 'CANCELLED';
            $this->roundCompletedAt = date('Y-m-d H:i:s');
        }
        $this->status = 'FINISHED';
    }

    public function hasPlayer(int $userId): bool
    {
        return isset($this->players[$userId]);
    }

    public function isDeadlineExpired(?int $now = null): bool
    {
        return $this->deadlineAt !== null && $this->deadlineAt > 0
            && ($now ?? time()) >= $this->deadlineAt;
    }

    private function openRound(int $number, string $scenario): void
    {
        $this->currentRoundId = 0;
        $this->roundNumber = $number;
        $this->roundStatus = 'OPEN';
        $this->scenario = $scenario;
        $this->deadlineAt = time() + $this->roundDurationSeconds;
        $this->roundCompletedAt = null;
    }
}
