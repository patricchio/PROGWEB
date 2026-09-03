<?php

class VView
{
    private Smarty $smarty;

    /**
     * Inizializza il motore di template Smarty configurando le directory.
     */
    public function __construct(string $projectRoot)
    {
        require_once $projectRoot . '/lib/smarty/Smarty.class.php';

        $compileDirectory = $projectRoot . '/storage/smarty-compile';
        if (!is_dir($compileDirectory)) {
            mkdir($compileDirectory, 0775, true);
        }

        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($projectRoot . '/templates');
        $this->smarty->setCompileDir($compileDirectory);
    }

    /**
     * Renderizza un template Smarty passandogli i dati forniti.
     */
    public function render(string $template, array $data = []): void
    {
        $this->smarty->assign($data);
        $this->smarty->assign('current_user', FSession::user());
        $this->smarty->assign('flash', FSession::consumeFlash());
        $this->smarty->assign('page_template', $template);
        $this->smarty->display('layout.tpl');
    }

    /**
     * Invia una risposta JSON al client con il codice di stato HTTP specificato.
     */
    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
