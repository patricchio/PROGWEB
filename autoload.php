<?php

/**
 * Registra la funzione di autoload per le classi dell'applicazione.
 * Cerca le classi nelle cartelle specificate (Presentation, Control, Entity, Foundation).
 */
spl_autoload_register(function ($className) {
    // Definizione delle cartelle in cui cercare le classi
    $folders = [
        __DIR__ . '/app/Presentation',
        __DIR__ . '/app/Control',
        __DIR__ . '/app/Entity',
        __DIR__ . '/app/Foundation',
    ];

    // Cerca il file corrispondente alla classe in ogni cartella e lo include se esiste
    foreach ($folders as $folder) {
        $file = $folder . '/' . $className . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
