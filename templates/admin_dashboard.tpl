<section class="dashboard-shell">
    <div class="dashboard-heading">
        <h1>Pannello Moderatore</h1>
        <p>Gestisci le partite in corso e gli account degli utenti.</p>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-title"><h2>Partite Attive ({$active_games|count})</h2></div>
            {if empty($active_games)}
                <p class="field-help">Nessuna partita attiva al momento.</p>
            {else}
                <ul class="player-list">
                    {foreach $active_games as $g}
                        <li style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>{$g->code}</strong> 
                                <small>{$g->status}</small><br>
                                <span class="field-help">Giocatori: {$g->playerCount} | Turni completati: {$g->roundsPlayed}</span>
                            </div>
                            <form method="post" action="{$base_url}/admin/game/{$g->code}/terminate" style="margin:0;">
                                <button type="submit" class="button button-danger button-small" onclick="return confirm('Vuoi davvero forzare la chiusura di questa partita?');">Termina</button>
                            </form>
                        </li>
                    {/foreach}
                </ul>
            {/if}
        </div>

        <div class="panel">
            <div class="panel-title"><h2>Utenti ({$users|count})</h2></div>
            {if empty($users)}
                <p class="field-help">Nessun utente trovato.</p>
            {else}
                <ul class="player-list">
                    {foreach $users as $u}
                        <li style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>{$u->username}</strong> {if $u->isAdmin}<small>ADMIN</small>{/if}<br>
                                <span class="field-help">{$u->email}</span>
                            </div>
                            {if !$u->isAdmin}
                                <form method="post" action="{$base_url}/admin/user/{$u->id}/delete" style="margin:0;">
                                    <button type="submit" class="button button-danger button-small" onclick="return confirm('Eliminare definitivamente l\'utente {$u->username}?');">Elimina</button>
                                </form>
                            {/if}
                        </li>
                    {/foreach}
                </ul>
            {/if}
        </div>
    </div>
</section>
