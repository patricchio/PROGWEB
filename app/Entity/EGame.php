<?php

class EGame
{
    /**
     * Round in attesa di persistenza (impostato da applyResults, consumato da
     * FPersistentManager::mutateGame). Non fa parte dello stato permanente
     * dell'entità: viene azzerato non appena il turno è stato salvato.
     */
    public ?array $pendingRound = null;

    /**
     * Inizializza un'istanza della partita con tutti i suoi dati e stato.
     * $players è indicizzato per user_id.
     */
    public function __construct(
        public int $id,
        public string $code,
        public int $hostUserId,
        public string $status,
        public string $phase,
        public int $maxPlayers,
        public int $initialLives,
        public int $roundDurationSeconds,
        public int $round,
        public int $roundsPlayed,
        public ?string $scenario,
        public ?int $deadlineAt,
        public ?int $winnerUserId,
        public ?string $winnerUsername,
        public array $players,
        public array $lastResults,
        public string $lastJudgmentSource = 'fallback',
        public string $lastStorySource = 'fallback',
        public ?int $playerCount = null
    ) {
    }

    /**
     * Crea una nuova partita impostandola nello stato di LOBBY.
     */
    public static function create(string $code, array $host, int $maxPlayers, int $lives,
        int $roundDuration = 30): self
    {
        $hostId = (int) $host['id'];
        return new self(
            0, $code, $hostId, 'LOBBY', 'LOBBY', $maxPlayers, $lives, $roundDuration,
            0, 0, null, null, null, null,
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

    /**
     * Ricostruisce un oggetto partita a partire dalla riga di `games` e dai
     * giocatori/risultati già caricati da FPersistentManager.
     */
    public static function fromRow(array $row, array $players, array $lastResults,
        string $lastJudgmentSource = 'fallback', string $lastStorySource = 'fallback'): self
    {
        $winnerUserId = $row['winner_user_id'] !== null ? (int) $row['winner_user_id'] : null;
        $winnerUsername = $winnerUserId !== null ? ($players[$winnerUserId]['username'] ?? null) : null;

        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['host_user_id'],
            (string) $row['status'],
            (string) $row['phase'],
            (int) $row['max_players'],
            (int) $row['initial_lives'],
            (int) $row['round_duration_seconds'],
            (int) $row['round'],
            (int) $row['rounds_played'],
            $row['scenario'] !== null ? (string) $row['scenario'] : null,
            $row['deadline_at'] !== null ? (int) $row['deadline_at'] : null,
            $winnerUserId,
            $winnerUsername,
            $players,
            $lastResults,
            $lastJudgmentSource,
            $lastStorySource
        );
    }

    /**
     * Ricostruisce una versione leggera (senza giocatori né risultati) usata
     * per gli elenchi, con il solo conteggio dei partecipanti.
     */
    public static function fromSummaryRow(array $row): self
    {
        $game = self::fromRow($row, [], []);
        $game->playerCount = (int) $row['player_count'];
        return $game;
    }

    /**
     * Aggiunge un nuovo giocatore alla partita se c'è spazio e non è già iniziata.
     */
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

    /**
     * Avvia la partita cambiando lo stato in ACTIVE e impostando il primo scenario.
     */
    public function start(string $scenario): void
    {
        if ($this->status !== 'LOBBY') {
            throw new DomainException('La partita è già iniziata.');
        }
        $this->status = 'ACTIVE';
        $this->phase = 'OPEN';
        $this->round = 1;
        $this->scenario = $scenario;
        $this->deadlineAt = time() + $this->roundDurationSeconds;
    }

    /**
     * Registra la risposta (azione) di un giocatore per il turno corrente.
     */
    public function submitAnswer(int $userId, string $answer, bool $automatic = false): void
    {
        if ($this->status !== 'ACTIVE' || $this->phase !== 'OPEN') {
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

    /**
     * Prepara il turno per la valutazione, penalizzando chi non ha risposto in tempo.
     */
    public function prepareEvaluation(): void
    {
        if ($this->status !== 'ACTIVE' || $this->phase !== 'OPEN') {
            throw new DomainException('Il turno è già in valutazione.');
        }
        foreach ($this->players as &$player) {
            if ($player['lives'] > 0 && trim((string) ($player['answer'] ?? '')) === '') {
                $player['answer'] = '[NESSUNA RISPOSTA ENTRO IL TEMPO]';
            }
        }
        unset($player);
        $this->phase = 'EVALUATING';
    }

    /**
     * Applica i risultati calcolati dall'IA, aggiorna le vite e chiude il turno o la partita.
     */
    public function applyResults(array $results, string $judgmentSource = 'fallback',
        string $storySource = 'fallback'): void
    {
        if ($this->status !== 'ACTIVE' || $this->phase !== 'EVALUATING') {
            throw new DomainException('Il turno non può essere valutato.');
        }

        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[(int) $result['player_id']] = $result;
        }

        $roundResults = [];
        foreach ($this->players as $userId => &$player) {
            if ($player['lives'] <= 0) {
                continue;
            }
            $result = $resultMap[$userId] ?? null;
            if ($result === null) {
                throw new DomainException('La valutazione AI è incompleta.');
            }
            $missedDeadline = substr((string) $player['answer'], 0, 17) === '[NESSUNA RISPOSTA';
            if ($missedDeadline) {
                $result['outcome'] = 'LOSE_LIFE';
            }
            if ($result['outcome'] === 'LOSE_LIFE') {
                $player['lives'] = max(0, (int) $player['lives'] - 1);
            }
            $roundResults[] = [
                'user_id' => $userId,
                'username' => (string) $player['username'],
                'outcome' => (string) $result['outcome'],
                'answer' => (string) $player['answer'],
                'story' => (string) ($result['story'] ?? ''),
                'lives' => (int) $player['lives'],
            ];
            $player['answer'] = null;
        }
        unset($player);

        $this->pendingRound = [
            'round_number' => $this->round,
            'scenario' => (string) $this->scenario,
            'judgment_source' => $judgmentSource,
            'story_source' => $storySource,
            'results' => $roundResults,
        ];
        $this->lastResults = array_map(function ($result) { return [
            'player_id' => $result['user_id'],
            'username' => $result['username'],
            'outcome' => $result['outcome'],
            'answer' => $result['answer'],
            'story' => $result['story'],
            'lives' => $result['lives'],
        ]; }, $roundResults);
        $this->lastJudgmentSource = $judgmentSource;
        $this->lastStorySource = $storySource;
        $this->roundsPlayed++;

        $alive = array_values(array_filter($this->players,
            function ($player) { return (int) $player['lives'] > 0; }));
        $finished = count($this->players) === 1 ? count($alive) === 0 : count($alive) <= 1;
        if ($finished) {
            $this->status = 'FINISHED';
            $this->phase = 'FINISHED';
            $this->winnerUserId = count($alive) === 1 ? (int) $alive[0]['user_id'] : null;
            $this->winnerUsername = count($alive) === 1 ? (string) $alive[0]['username'] : null;
        } else {
            $this->phase = 'RESULTS';
        }
    }

    /**
     * Inizia un nuovo round impostando un nuovo scenario e resettando il timer.
     */
    public function nextRound(string $scenario): void
    {
        if ($this->status !== 'ACTIVE' || $this->phase !== 'RESULTS') {
            throw new DomainException('Non è possibile aprire un nuovo turno.');
        }
        $this->round++;
        $this->scenario = $scenario;
        $this->phase = 'OPEN';
        $this->deadlineAt = time() + $this->roundDurationSeconds;
        $this->lastResults = [];
    }

    /**
     * Controlla se un determinato utente partecipa a questa partita.
     */
    public function hasPlayer(int $userId): bool
    {
        return isset($this->players[$userId]);
    }

    /**
     * Verifica se il tempo limite per il turno corrente è scaduto.
     */
    public function isDeadlineExpired(?int $now = null): bool
    {
        return $this->deadlineAt !== null && $this->deadlineAt > 0
            && ($now ?? time()) >= $this->deadlineAt;
    }
}
