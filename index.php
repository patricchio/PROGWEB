<?php


require_once __DIR__ . '/autoload.php';

FSession::start();

$view = new VView(__DIR__);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

if ($basePath !== '' && $basePath !== '/' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$routes = [
    ['GET', '#^/$#', CGame::class, 'home'],
    ['POST', '#^/games$#', CGame::class, 'create'],
    ['POST', '#^/join$#', CGame::class, 'join'],
    ['GET', '#^/game/([A-Za-z0-9]{6})$#', CGame::class, 'show'],
    ['POST', '#^/game/([A-Za-z0-9]{6})/start$#', CGame::class, 'start'],
    ['POST', '#^/game/([A-Za-z0-9]{6})/answer$#', CGame::class, 'answer'],
    ['POST', '#^/game/([A-Za-z0-9]{6})/evaluate$#', CGame::class, 'evaluate'],
    ['POST', '#^/game/([A-Za-z0-9]{6})/next$#', CGame::class, 'nextRound'],
    ['POST', '#^/game/([A-Za-z0-9]{6})/delete$#', CGame::class, 'delete'],
    ['GET', '#^/api/game/([A-Za-z0-9]{6})$#', CGame::class, 'state'],
    ['GET', '#^/login$#', CAuth::class, 'showLogin'],
    ['POST', '#^/login$#', CAuth::class, 'login'],
    ['GET', '#^/register$#', CAuth::class, 'showRegister'],
    ['POST', '#^/register$#', CAuth::class, 'register'],
    ['POST', '#^/logout$#', CAuth::class, 'logout'],
    ['GET', '#^/admin$#', CAdmin::class, 'dashboard'],
    ['POST', '#^/admin/game/([A-Za-z0-9]{6})/terminate$#', CAdmin::class, 'terminateGame'],
    ['POST', '#^/admin/user/([0-9]+)/delete$#', CAdmin::class, 'deleteUser'],
];

foreach ($routes as [$method, $pattern, $controllerClass, $action]) {
    if ($requestMethod === $method && preg_match($pattern, $requestPath, $matches) === 1) {
        array_shift($matches);
        $controller = new $controllerClass($view, $basePath);
        $params = array_values($matches);
        call_user_func_array([$controller, $action], $params);
        exit;
    }
}

http_response_code(404);
$view->render('error.tpl', [
    'page_title' => 'Pagina non trovata',
    'status_code' => 404,
    'message' => 'La pagina richiesta non esiste.',
    'base_url' => $basePath,
]);
