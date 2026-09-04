<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$config = require $projectRoot . '/config/config.php';
$errors = [];

if (($config['ai']['provider'] ?? '') !== 'ollama') {
    $errors[] = 'Il provider AI configurato non e Ollama.';
}

try {
    $database = $config['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name']
    );
    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $tableCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )->fetchColumn();
    if ($tableCount < 5) {
        $errors[] = "Il database contiene solo {$tableCount} tabelle; importa database/schema.sql.";
    } else {
        fwrite(STDOUT, "[OK] MySQL: database raggiungibile ({$tableCount} tabelle).\n");
    }
} catch (Throwable $exception) {
    $errors[] = 'MySQL non raggiungibile: ' . $exception->getMessage();
}

$ollamaUrl = rtrim((string) ($config['ai']['ollama_url'] ?? ''), '/');
$ollamaModel = (string) ($config['ai']['ollama_model'] ?? '');
$handle = curl_init($ollamaUrl . '/api/tags');
curl_setopt_array($handle, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 5,
]);
$body = curl_exec($handle);
$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($handle);
curl_close($handle);

if (!is_string($body) || $status !== 200) {
    $errors[] = 'Ollama non raggiungibile: ' . ($curlError !== '' ? $curlError : "HTTP {$status}");
} else {
    $models = json_decode($body, true)['models'] ?? [];
    $installedNames = array_map(
        static fn(array $model): string => (string) ($model['name'] ?? ''),
        is_array($models) ? $models : []
    );
    $acceptedNames = [$ollamaModel, $ollamaModel . ':latest'];
    if (array_intersect($acceptedNames, $installedNames) === []) {
        $errors[] = "Il modello Ollama '{$ollamaModel}' non e installato.";
    } else {
        fwrite(STDOUT, "[OK] Ollama: modello {$ollamaModel} disponibile.\n");
    }
}

$storageDirectory = $projectRoot . '/storage';
if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0775, true) && !is_dir($storageDirectory)) {
    $errors[] = 'Impossibile creare la cartella storage.';
} elseif (!is_writable($storageDirectory)) {
    $errors[] = 'La cartella storage non e scrivibile.';
} else {
    fwrite(STDOUT, "[OK] Storage: cartella scrivibile.\n");
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[ERRORE] {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[OK] Controlli preliminari completati.\n");
