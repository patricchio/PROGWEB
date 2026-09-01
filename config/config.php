<?php

declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';
$config = [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'death_by_ai',
        'user' => 'root',
        'password' => '',
    ],
    'ai' => [
        // Motore attivo per i test. Per Ollama usare: 'provider' => 'ollama'.
        'provider' => 'openai',
        'openai_url' => 'https://api.openai.com/v1/chat/completions',
        'openai_api_key' => '',
        'openai_model' => 'gpt-4o-mini',
        'max_output_tokens' => 700,

        // Configurazione alternativa locale (non attiva finché provider è openai).
        'ollama_url' => 'http://127.0.0.1:11434',
        'ollama_model' => 'llama3',
    ],
];

if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
