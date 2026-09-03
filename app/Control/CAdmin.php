<?php

class CAdmin
{
    private $view;
    private $baseUrl;

    public function __construct($view, $baseUrl)
    {
        $this->view = $view;
        $this->baseUrl = $baseUrl;
    }

    private function requireAdmin()
    {
        $user = FSession::user();
        if ($user === null || empty($user['is_admin'])) {
            header('Location: ' . $this->baseUrl . '/');
            exit;
        }
        return $user;
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $manager = new FPersistentManager();
        
        try {
            $activeGames = $manager->findAllActiveGames();
            $users = $manager->findAllUsers();
        } catch (Exception $e) {
            $activeGames = [];
            $users = [];
            FSession::flash('error', 'Errore caricamento dati: ' . $e->getMessage());
        }

        $this->view->render('admin_dashboard.tpl', [
            'page_title' => 'Pannello Moderatore - Death by AI',
            'base_url' => $this->baseUrl,
            'active_games' => $activeGames,
            'users' => $users,
        ]);
    }

    public function terminateGame(string $code): void
    {
        $this->requireAdmin();
        
        try {
            (new FPersistentManager())->mutateGame(strtoupper($code), function ($game) {
                if ($game->status === 'FINISHED') {
                    throw new Exception('La partita è già terminata.');
                }
                $game->status = 'FINISHED';
                $game->phase = 'FINISHED';
            });
            FSession::flash('success', 'Partita ' . strtoupper($code) . ' terminata forzatamente.');
        } catch (Exception $e) {
            FSession::flash('error', $e->getMessage());
        }
        
        header('Location: ' . $this->baseUrl . '/admin');
        exit;
    }

    public function deleteUser(string $id): void
    {
        $admin = $this->requireAdmin();
        $userId = (int) $id;
        
        if ($admin['id'] == $userId) {
            FSession::flash('error', 'Non puoi eliminare il tuo stesso account.');
        } else {
            try {
                (new FPersistentManager())->deleteUser($userId);
                FSession::flash('success', 'Utente eliminato con successo.');
            } catch (Exception $e) {
                FSession::flash('error', 'Impossibile eliminare l\'utente: ' . $e->getMessage());
            }
        }
        
        header('Location: ' . $this->baseUrl . '/admin');
        exit;
    }
}
