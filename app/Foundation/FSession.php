<?php

declare(strict_types=1);

final class FSession
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionDirectory = dirname(__DIR__, 2) . '/storage/sessions';
            if (!is_dir($sessionDirectory)) {
                mkdir($sessionDirectory, 0775, true);
            }
            session_save_path($sessionDirectory);
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(EUser $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user->id, 'username' => $user->username, 'email' => $user->email];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'],
                $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public static function requireUser(string $baseUrl): array
    {
        $user = self::user();
        if ($user === null) {
            self::flash('error', 'Accedi per continuare.');
            header('Location: ' . $baseUrl . '/login');
            exit;
        }
        return $user;
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }
}
