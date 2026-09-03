CREATE DATABASE IF NOT EXISTS death_by_ai
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE death_by_ai;

-- Le tabelle di partita vengono ricreate: lo schema precedente teneva l'intero
-- stato di gioco in una sola colonna JSON (games.state_json), qui invece viene
-- normalizzato su più tabelle relazionali. Gli account utente restano intatti.
DROP TABLE IF EXISTS round_results;
DROP TABLE IF EXISTS rounds;
DROP TABLE IF EXISTS game_players;
DROP TABLE IF EXISTS games;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(24) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Una riga per partita: solo metadati e stato corrente del turno in gioco.
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code CHAR(6) NOT NULL UNIQUE,
    host_user_id INT UNSIGNED NOT NULL,
    status ENUM('LOBBY', 'ACTIVE', 'FINISHED') NOT NULL DEFAULT 'LOBBY',
    phase ENUM('LOBBY', 'OPEN', 'EVALUATING', 'RESULTS', 'FINISHED') NOT NULL DEFAULT 'LOBBY',
    max_players TINYINT UNSIGNED NOT NULL,
    initial_lives TINYINT UNSIGNED NOT NULL,
    round_duration_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    round INT UNSIGNED NOT NULL DEFAULT 0,
    rounds_played INT UNSIGNED NOT NULL DEFAULT 0,
    scenario TEXT NULL,
    deadline_at INT UNSIGNED NULL,
    winner_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    FOREIGN KEY (host_user_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (winner_user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_games_host (host_user_id),
    INDEX idx_games_status (status)
) ENGINE=InnoDB;

-- Un giocatore per riga per partita: vite correnti e risposta del turno aperto.
CREATE TABLE game_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    lives TINYINT UNSIGNED NOT NULL,
    current_answer VARCHAR(700) NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id)
        REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_game_player (game_id, user_id)
) ENGINE=InnoDB;

-- Un turno chiuso e valutato per partita (sostituisce l'array "history" del JSON).
CREATE TABLE rounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    round_number INT UNSIGNED NOT NULL,
    scenario TEXT NOT NULL,
    judgment_source VARCHAR(20) NOT NULL,
    story_source VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id)
        REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_game_round (game_id, round_number)
) ENGINE=InnoDB;

-- Esito e racconto individuale di ogni giocatore per un turno chiuso.
CREATE TABLE round_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id INT UNSIGNED NOT NULL,
    game_player_id INT UNSIGNED NOT NULL,
    answer VARCHAR(700) NOT NULL,
    outcome ENUM('SAFE', 'LOSE_LIFE') NOT NULL,
    story TEXT NOT NULL,
    lives_after TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (round_id)
        REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (game_player_id)
        REFERENCES game_players(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_round_player (round_id, game_player_id)
) ENGINE=InnoDB;
