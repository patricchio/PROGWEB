<?php

return [
    'database' => ['password' => ''],
    'ai' => [
        'provider' => 'ollama',
        'ollama_url' => 'http://127.0.0.1:11434',
        'ollama_model' => 'llama3',

        // Per OpenAI impostare provider=openai e aggiungere URL, modello e chiave.
        // Non versionare mai config/config.local.php.
    ],
];
