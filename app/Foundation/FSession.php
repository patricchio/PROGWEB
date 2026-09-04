<?php

class FSession
{
    /**
     * Avvia la sessione se non è già stata avviata, configurando i cookie e il path sicuro.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Esegue il login dell'utente salvandolo in sessione.
     */
    public static function login(EUser $user): void
    {
        $_SESSION['user'] = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => $user->isAdmin,
        ];
    }

    /**
     * Effettua il logout distruggendo la sessione e il cookie associato.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Restituisce i dati dell'utente attualmente loggato, se presente.
     */
    public static function user(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    /**
     * Verifica che un utente sia loggato; in caso contrario reindirizza al login.
     */
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


    /**
     * Imposta un messaggio flash temporaneo da mostrare alla successiva richiesta.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Recupera e cancella il messaggio flash corrente in modo che venga mostrato una sola volta.
     */
    public static function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }
}
