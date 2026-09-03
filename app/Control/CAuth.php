<?php

class CAuth
{
    /**
     * Costruttore: riceve la vista e l'URL base del sito.
     */
    private $view;
    private $baseUrl;

    public function __construct($view, $baseUrl)
    {
        $this->view = $view;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Mostra la pagina di login (se non si è già loggati).
     */
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

    /**
     * Mostra la pagina di registrazione (se non si è già loggati).
     */
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

    /**
     * Gestisce la sottomissione del form di registrazione.
     */
    public function register(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = $this->validate($username, $email, $password, true);

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
            } catch (Exception) {
                $errors[] = 'Database non disponibile. Avvia MySQL da XAMPP e importa database/schema.sql.';
            }
        }

        $this->renderForm('register', $errors, ['username' => $username, 'email' => $email]);
    }

    /**
     * Gestisce la sottomissione del form di login.
     */
    public function login(): void
    {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = $this->validate('', $email, $password, false);

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
            } catch (Exception) {
                $errors[] = 'Database non disponibile. Avvia MySQL da XAMPP.';
            }
        }

        $this->renderForm('login', $errors, ['email' => $email]);
    }

    /**
     * Effettua il logout dell'utente in sicurezza, controllando il CSRF.
     */
    public function logout(): void
    {
        FSession::logout();
        header('Location: ' . $this->baseUrl . '/');
    }

    /**
     * Controlla la validità dei dati (email, password, username).
     */
    private function validate($username, $email, $password, $registration)
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

    /**
     * Mostra nuovamente il form (login o registrazione) evidenziando gli errori.
     */
    private function renderForm($mode, $errors, $old)
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

    /**
     * Reindirizza l'utente a un percorso specificato e termina l'esecuzione.
     */
    private function redirect($path)
    {
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}
