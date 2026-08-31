<section class="auth-shell">
    <div class="auth-intro">
        <span class="eyebrow">La prossima storia ti aspetta</span>
        {if $mode === 'login'}
            <h1>Torna nella partita.</h1>
            <p>Accedi per creare una nuova sfida o raggiungere i tuoi amici.</p>
        {else}
            <h1>Crea il tuo giocatore.</h1>
            <p>Bastano pochi secondi. Le decisioni difficili arriveranno dopo.</p>
        {/if}
    </div>

    <form class="auth-card" method="post" action="{$base_url}/{if $mode === 'login'}login{else}register{/if}">
        <input type="hidden" name="csrf_token" value="{$csrf_token}">

        {if !empty($errors)}
            <div class="form-errors" role="alert">
                <strong>Controlla questi dati:</strong>
                <ul>
                    {foreach $errors as $error}<li>{$error}</li>{/foreach}
                </ul>
            </div>
        {/if}

        {if $mode === 'register'}
            <label for="username">Nome giocatore</label>
            <input id="username" name="username" type="text" maxlength="24" autocomplete="username" value="{$old.username|default:''}" required>
        {/if}

        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{$old.email|default:''}" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="{if $mode === 'login'}current-password{else}new-password{/if}" required>

        <button class="button button-full" type="submit">
            {if $mode === 'login'}Accedi{else}Crea account{/if}
        </button>

        <p class="auth-switch">
            {if $mode === 'login'}
                Non hai un account? <a href="{$base_url}/register">Registrati</a>
            {else}
                Hai già un account? <a href="{$base_url}/login">Accedi</a>
            {/if}
        </p>
    </form>
</section>
