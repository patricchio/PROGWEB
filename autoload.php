<?php

declare(strict_types=1);

spl_autoload_register(static function (string $className): void {
    $folders = [
        __DIR__ . '/app/Presentation',
        __DIR__ . '/app/Control',
        __DIR__ . '/app/Entity',
        __DIR__ . '/app/Foundation',
    ];

    foreach ($folders as $folder) {
        $file = $folder . '/' . $className . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

