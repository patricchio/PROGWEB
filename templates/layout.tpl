<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Death by AI: un gioco narrativo di sopravvivenza, da soli o con gli amici.">
    <title>{$page_title|default:'Death by AI'}</title>
    <link rel="stylesheet" href="{$base_url}/public/css/style.css">
    <script src="{$base_url}/public/js/app.js" defer></script>
</head>
<body data-base-url="{$base_url}">
    <header class="site-header">
        <a class="brand" href="{$base_url}/" aria-label="Death by AI - Home">
            <span class="brand-mark" aria-hidden="true">D</span>
            <span>DEATH BY <strong>AI</strong></span>
        </a>
        <nav class="site-nav" aria-label="Navigazione principale">
            {if $current_user}
                <span class="user-chip">{$current_user.username}</span>
                <form method="post" action="{$base_url}/logout">
                    <button class="nav-button" type="submit">Esci</button>
                </form>
            {else}
                <a href="{$base_url}/login">Accedi</a>
                <a class="button button-small" href="{$base_url}/register">Registrati</a>
            {/if}
        </nav>
    </header>

    <main>
        {if $flash}
            <div class="flash flash-{$flash.type}" role="status">{$flash.message}</div>
        {/if}
        {include file=$page_template}
    </main>

    <footer class="site-footer">
        <span>Progetto di Programmazione Web</span>
        <span>PHP · MySQL · Smarty · JavaScript</span>
    </footer>
</body>
</html>
