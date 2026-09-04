<?php

declare(strict_types=1);

/*
 * Router per il server PHP locale usato dalla demo pubblica.
 * Serve direttamente soltanto gli asset contenuti in /public e inoltra tutte
 * le altre richieste al Front Controller, così file di configurazione, SQL,
 * documentazione e metadati Git non diventano scaricabili.
 */

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline'");

$accessPassword = (string) getenv('DEMO_ACCESS_PASSWORD');
if ($accessPassword !== '') {
    $accessUsername = (string) (getenv('DEMO_ACCESS_USERNAME') ?: 'demo');
    $suppliedUsername = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $suppliedPassword = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

    if (!hash_equals($accessUsername, $suppliedUsername)
        || !hash_equals($accessPassword, $suppliedPassword)) {
        header('WWW-Authenticate: Basic realm="Death by AI demo", charset="UTF-8"');
        http_response_code(401);
        echo 'Autenticazione richiesta per accedere alla demo.';
        return true;
    }
}

$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicRoot = realpath(__DIR__ . '/public');
$requestedFile = realpath(__DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath));

if ($publicRoot !== false && $requestedFile !== false && is_file($requestedFile)) {
    $normalizedPublicRoot = strtolower(rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    $normalizedRequestedFile = strtolower($requestedFile);
    if (str_starts_with($normalizedRequestedFile, $normalizedPublicRoot)) {
        return false;
    }
}

// Il Front Controller calcola il base URL usando SCRIPT_NAME.
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
return true;
