<?php

return [
    'database' => ['password' => ''],
    'ai' => [
        'provider' => 'openai',
        'openai_model' => 'gpt-4o-mini',
        'openai_api_key' => '',

        // Per usare Ollama, commentare le tre righe sopra e decommentare queste:
        // 'provider' => 'ollama',
        // 'ollama_url' => 'http://127.0.0.1:11434',
        // 'ollama_model' => 'llama3',
    ],
];
