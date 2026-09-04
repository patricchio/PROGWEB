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

-- Una riga per partita: configurazione, stato generale e risultato finale.
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code CHAR(6) NOT NULL UNIQUE,
    host_user_id INT UNSIGNED NOT NULL,
    status ENUM('LOBBY', 'ACTIVE', 'FINISHED') NOT NULL DEFAULT 'LOBBY',
    max_players TINYINT UNSIGNED NOT NULL,
    initial_lives TINYINT UNSIGNED NOT NULL,
    round_duration_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    rounds_played INT UNSIGNED NOT NULL DEFAULT 0,
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

-- Un giocatore per riga per partita: partecipazione e vite correnti.
CREATE TABLE game_players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    lives TINYINT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id)
        REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_game_player (game_id, user_id)
) ENGINE=InnoDB;

-- Ogni turno viene creato quando si apre e conserva il proprio stato.
CREATE TABLE rounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    round_number INT UNSIGNED NOT NULL,
    status ENUM('OPEN', 'EVALUATING', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
    scenario TEXT NOT NULL,
    deadline_at INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (game_id)
        REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_game_round (game_id, round_number)
) ENGINE=InnoDB;

-- Risposta e successivo esito di un giocatore nello specifico turno.
CREATE TABLE round_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id INT UNSIGNED NOT NULL,
    game_player_id INT UNSIGNED NOT NULL,
    answer VARCHAR(700) NULL,
    answered_at DATETIME NULL,
    outcome ENUM('SAFE', 'LOSE_LIFE') NULL,
    story TEXT NULL,
    lives_after TINYINT UNSIGNED NULL,
    FOREIGN KEY (round_id)
        REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (game_player_id)
        REFERENCES game_players(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_round_player (round_id, game_player_id)
) ENGINE=InnoDB;
