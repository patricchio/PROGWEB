<section class="auth-shell">
    {if $mode === 'login'}
        <h1>Accedi</h1>
    {else}
        <h1>Registrati</h1>
    {/if}

    <form class="auth-card" method="post" action="{$base_url}/{if $mode === 'login'}login{else}register{/if}">

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
