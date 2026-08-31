<?php

declare(strict_types=1);

final class CAuth
{
    public function __construct(
        private VView $view,
        private string $baseUrl
    ) {
    }

    public function showLogin(): void
    {
        if (FSession::user() !== null) {
            $this->redirect('/');
        }
        $this->view->render('auth.tpl', [
            'page_title' => 'Accedi - Death by AI',
            'base_url' => $this->baseUrl,
            'mode' => 'login',
        ]);
    }

    public function showRegister(): void
    {
        if (FSession::user() !== null) {
            $this->redirect('/');
        }
        $this->view->render('auth.tpl', [
            'page_title' => 'Registrati - Death by AI',
            'base_url' => $this->baseUrl,
            'mode' => 'register',
        ]);
    }

    public function register(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = $this->validate($username, $email, $password, true);

        if (!FSession::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $errors[] = 'La pagina è scaduta. Riprova.';
        }

        if ($errors === []) {
            try {
                $manager = new FPersistentManager();
                if ($manager->usernameOrEmailExists($username, $email)) {
                    $errors[] = 'Nome giocatore o email già utilizzati.';
                } else {
                    $user = $manager->createUser($username, $email, password_hash($password, PASSWORD_DEFAULT));
                    FSession::login($user);
                    FSession::flash('success', 'Account creato. Benvenuto, ' . $user->username . '!');
                    $this->redirect('/');
                }
            } catch (Throwable) {
                $errors[] = 'Database non disponibile. Avvia MySQL da XAMPP e importa database/schema.sql.';
            }
        }

        $this->renderForm('register', $errors, ['username' => $username, 'email' => $email]);
    }

    public function login(): void
    {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = $this->validate('', $email, $password, false);

        if (!FSession::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $errors[] = 'La pagina è scaduta. Riprova.';
        }

        if ($errors === []) {
            try {
                $user = (new FPersistentManager())->findUserByEmail($email);
                if ($user === null || !password_verify($password, $user->passwordHash)) {
                    $errors[] = 'Email o password non corrette.';
                } else {
                    FSession::login($user);
                    FSession::flash('success', 'Bentornato, ' . $user->username . '!');
                    $this->redirect('/');
                }
            } catch (Throwable) {
                $errors[] = 'Database non disponibile. Avvia MySQL da XAMPP.';
            }
        }

        $this->renderForm('login', $errors, ['email' => $email]);
    }

    public function logout(): void
    {
        if (!FSession::verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }
        FSession::logout();
        header('Location: ' . $this->baseUrl . '/');
    }

    private function validate(string $username, string $email, string $password, bool $registration): array
    {
        $errors = [];
        if ($registration && preg_match('/^[A-Za-z0-9_]{3,24}$/', $username) !== 1) {
            $errors[] = 'Il nome deve contenere 3-24 lettere, numeri o underscore.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Inserisci un indirizzo email valido.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'La password deve contenere almeno 8 caratteri.';
        }
        return $errors;
    }

    private function renderForm(string $mode, array $errors, array $old): void
    {
        http_response_code(422);
        $this->view->render('auth.tpl', [
            'page_title' => ($mode === 'login' ? 'Accedi' : 'Registrati') . ' - Death by AI',
            'base_url' => $this->baseUrl,
            'mode' => $mode,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
