<?php

declare(strict_types=1);

final class EGame
{
    public function __construct(
        public int $id,
        public string $code,
        public int $hostUserId,
        public string $status,
        public int $maxPlayers,
        public int $initialLives,
        public int $madnessLevel,
        public string $scenarioType,
        public ?string $scenarioValue,
        public array $state
    ) {
    }

    public static function create(string $code, array $host, int $maxPlayers, int $lives,
        int $madness, string $scenarioType, ?string $scenarioValue, int $roundDuration = 30): self
    {
        return new self(0, $code, (int) $host['id'], 'LOBBY', $maxPlayers, $lives, $madness,
            $scenarioType, $scenarioValue, [
                'phase' => 'LOBBY',
                'round' => 0,
                'round_duration_seconds' => $roundDuration,
                'deadline_at' => null,
                'scenario' => null,
                'players' => [[
                    'id' => (int) $host['id'],
                    'username' => (string) $host['username'],
                    'lives' => $lives,
                    'answer' => null,
                ]],
                'last_results' => [],
                'history' => [],
            ]);
    }

    public static function fromRow(array $row): self
    {
        $state = json_decode((string) $row['state_json'], true, 512, JSON_THROW_ON_ERROR);
        $state += [
            'round_duration_seconds' => 30,
            'deadline_at' => null,
            'last_ai_source' => 'fallback',
        ];
        return new self((int) $row['id'], (string) $row['code'], (int) $row['host_user_id'],
            (string) $row['status'], (int) $row['max_players'], (int) $row['initial_lives'],
            (int) $row['madness_level'], (string) $row['scenario_type'],
            $row['scenario_value'] === null ? null : (string) $row['scenario_value'], $state);
    }

    public function addPlayer(array $user): void
    {
        if ($this->status !== 'LOBBY') {
            throw new DomainException('La partita è già iniziata.');
        }
        if ($this->playerIndex((int) $user['id']) !== null) {
            return;
        }
        if (count($this->state['players']) >= $this->maxPlayers) {
            throw new DomainException('La lobby è piena.');
        }
        $this->state['players'][] = [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'lives' => $this->initialLives,
            'answer' => null,
        ];
    }

    public function start(string $scenario): void
    {
        if ($this->status !== 'LOBBY') {
            throw new DomainException('La partita è già iniziata.');
        }
        $this->status = 'ACTIVE';
        $this->state['phase'] = 'OPEN';
        $this->state['round'] = 1;
        $this->state['scenario'] = $scenario;
        $this->state['deadline_at'] = time() + (int) $this->state['round_duration_seconds'];
    }

    public function submitAnswer(int $userId, string $answer): void
    {
        if ($this->status !== 'ACTIVE' || $this->state['phase'] !== 'OPEN') {
            throw new DomainException('Il turno non accetta risposte.');
        }
        if ($this->isDeadlineExpired()) {
            throw new DomainException('Il tempo è scaduto: la risposta non può più essere modificata.');
        }
        $index = $this->playerIndex($userId);
        if ($index === null || $this->state['players'][$index]['lives'] <= 0) {
            throw new DomainException('Non puoi rispondere in questa partita.');
        }
        $this->state['players'][$index]['answer'] = $answer;
    }

    public function prepareEvaluation(): void
    {
        if ($this->status !== 'ACTIVE' || $this->state['phase'] !== 'OPEN') {
            throw new DomainException('Il turno è già in valutazione.');
        }
        foreach ($this->state['players'] as &$player) {
            if ($player['lives'] > 0 && trim((string) ($player['answer'] ?? '')) === '') {
                $player['answer'] = '[NESSUNA RISPOSTA ENTRO IL TEMPO]';
            }
        }
        unset($player);
        $this->state['phase'] = 'EVALUATING';
    }

    public function applyResults(array $results, string $source = 'fallback'): void
    {
        if ($this->status !== 'ACTIVE' || $this->state['phase'] !== 'EVALUATING') {
            throw new DomainException('Il turno non può essere valutato.');
        }

        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[(int) $result['player_id']] = $result;
        }

        $roundResults = [];
        foreach ($this->state['players'] as &$player) {
            if ($player['lives'] <= 0) {
                continue;
            }
            $result = $resultMap[(int) $player['id']] ?? null;
            if ($result === null) {
                throw new DomainException('La valutazione AI è incompleta.');
            }
            $missedDeadline = str_starts_with((string) $player['answer'], '[NESSUNA RISPOSTA');
            if ($missedDeadline) {
                $result['outcome'] = 'LOSE_LIFE';
            }
            if ($result['outcome'] === 'LOSE_LIFE') {
                $player['lives'] = max(0, (int) $player['lives'] - 1);
            }
            $roundResults[] = [
                'player_id' => (int) $player['id'],
                'username' => (string) $player['username'],
                'outcome' => (string) $result['outcome'],
                'answer' => (string) $player['answer'],
                'story' => (string) ($result['story'] ?? ''),
                'lives' => (int) $player['lives'],
            ];
            $player['answer'] = null;
        }
        unset($player);

        $record = [
            'round' => (int) $this->state['round'],
            'scenario' => (string) $this->state['scenario'],
            'results' => $roundResults,
        ];
        $this->state['last_results'] = $roundResults;
        $this->state['last_scenario'] = (string) $this->state['scenario'];
        $this->state['last_ai_source'] = $source;
        $record['ai_source'] = $source;
        $this->state['history'][] = $record;

        $alive = array_values(array_filter($this->state['players'],
            static fn (array $player): bool => (int) $player['lives'] > 0));
        $finished = count($this->state['players']) === 1 ? count($alive) === 0 : count($alive) <= 1;
        if ($finished) {
            $this->status = 'FINISHED';
            $this->state['phase'] = 'FINISHED';
            $this->state['winner'] = count($alive) === 1 ? $alive[0]['username'] : null;
        } else {
            $this->state['phase'] = 'RESULTS';
        }
    }

    public function nextRound(string $scenario): void
    {
        if ($this->status !== 'ACTIVE' || $this->state['phase'] !== 'RESULTS') {
            throw new DomainException('Non è possibile aprire un nuovo turno.');
        }
        $this->state['round']++;
        $this->state['scenario'] = $scenario;
        $this->state['phase'] = 'OPEN';
        $this->state['deadline_at'] = time() + (int) $this->state['round_duration_seconds'];
        $this->state['last_results'] = [];
    }

    public function hasPlayer(int $userId): bool
    {
        return $this->playerIndex($userId) !== null;
    }

    public function allLivingPlayersAnswered(): bool
    {
        foreach ($this->state['players'] as $player) {
            if ($player['lives'] > 0 && trim((string) ($player['answer'] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    public function isDeadlineExpired(?int $now = null): bool
    {
        $deadline = (int) ($this->state['deadline_at'] ?? 0);
        return $deadline > 0 && ($now ?? time()) >= $deadline;
    }

    private function playerIndex(int $userId): ?int
    {
        foreach ($this->state['players'] as $index => $player) {
            if ((int) $player['id'] === $userId) {
                return $index;
            }
        }
        return null;
    }
}
