<section class="dashboard-shell">
    <div class="dashboard-heading">
        <span class="eyebrow">Ciao, {$current_user.username}</span>
        <h1>Che pericolo vuoi affrontare?</h1>
        <p>Crea una partita da solo o prepara una lobby per un massimo di cinque giocatori.</p>
    </div>

    <div class="dashboard-grid">
        <form class="panel game-setup" method="post" action="{$base_url}/games">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <div class="panel-title"><span>01</span><h2>Crea partita</h2></div>

            <div class="field-grid">
                <label>Giocatori
                    <select name="max_players">
                        <option value="1">1 · Single player</option>
                        <option value="2">2 giocatori</option>
                        <option value="3">3 giocatori</option>
                        <option value="4">4 giocatori</option>
                        <option value="5">5 giocatori</option>
                    </select>
                </label>
                <label>Vite iniziali
                    <select name="lives">
                        <option value="1">1 vita</option>
                        <option value="2" selected>2 vite</option>
                        <option value="3">3 vite</option>
                    </select>
                </label>
                <label>Tempo per turno
                    <select name="round_duration">
                        <option value="10">10 secondi</option>
                        <option value="20">20 secondi</option>
                        <option value="30" selected>30 secondi</option>
                        <option value="45">45 secondi</option>
                        <option value="60">60 secondi</option>
                    </select>
                </label>
            </div>

            <fieldset class="choice-group">
                <legend>Livello di follia dell’AI</legend>
                <label><input type="radio" name="madness" value="1"> 1 · Quasi razionale</label>
                <label><input type="radio" name="madness" value="2" checked> 2 · Imprevedibile</label>
                <label><input type="radio" name="madness" value="3"> 3 · Caos totale</label>
            </fieldset>

            <label>Scelta del tema
                <select name="scenario_type" data-scenario-type>
                    <option value="PRESET">Scegli un tema predefinito</option>
                    <option value="CUSTOM">Scrivi un tema personalizzato</option>
                    <option value="RANDOM">Lascia scegliere il tema all’AI</option>
                </select>
            </label>
            <p class="field-help" data-random-help hidden>L’AI sceglierà un tema nuovo e creerà una situazione di pericolo.</p>
            <label data-preset-field>Tema predefinito
                <select name="preset_value">
                    <option value="ZOMBIE">Apocalisse zombie</option>
                    <option value="SPACE">Spazio profondo</option>
                    <option value="FANTASY">Fantasy magico</option>
                    <option value="DISASTER">Catastrofe assurda</option>
                </select>
            </label>
            <label data-custom-field hidden>Tema personalizzato
                <textarea name="custom_value" minlength="3" maxlength="120" rows="2" placeholder="Es. parco divertimenti, scuola di magia, nave pirata..."></textarea>
            </label>

            <button class="button button-full" type="submit">Crea partita</button>
        </form>

        <form class="panel join-panel" method="post" action="{$base_url}/join">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <div class="panel-title"><span>02</span><h2>Entra con un codice</h2></div>
            <p>Fatti inviare il codice di sei caratteri dall’host.</p>
            <p class="field-help">Per provare due account sullo stesso PC usa una finestra anonima o un altro browser: due schede normali condividono la stessa sessione.</p>
            <label for="game-code">Codice invito</label>
            <input class="code-input" id="game-code" name="code" minlength="6" maxlength="12" pattern="[A-Za-z0-9 -]+" placeholder="A1B2C3" autocomplete="off" autocapitalize="characters" required>
            <button class="button button-secondary button-full" type="submit">Raggiungi la lobby</button>
        </form>
    </div>

    {if !empty($recent_games)}
        <section class="recent-games">
            <div class="panel-title"><span>03</span><h2>Le tue partite</h2></div>
            <div class="recent-grid">
                {foreach $recent_games as $recent}
                    <article class="recent-card">
                        <a class="recent-card-link" href="{$base_url}/game/{$recent->code}">
                            <span class="recent-code">{if $recent->maxPlayers === 1}Single player{else}{$recent->code}{/if}</span>
                            <strong>{if $recent->status === 'LOBBY'}Lobby{elseif $recent->status === 'ACTIVE'}In corso{else}Conclusa{/if}</strong>
                            <small>{$recent->state.players|count} giocatore/i · {$recent->state.history|count} turni</small>
                        </a>
                        {if $recent->status !== 'FINISHED'}
                            <button class="delete-game" type="button" data-delete-game="{$recent->code}" data-delete-label="{if $recent->maxPlayers === 1}Single player{else}{$recent->code}{/if}" aria-label="Elimina {if $recent->maxPlayers === 1}la partita single player{else}la partita {$recent->code}{/if}">×</button>
                        {/if}
                    </article>
                {/foreach}
            </div>
        </section>
    {/if}
</section>

<dialog class="confirm-dialog" data-delete-dialog>
    <form method="post" data-delete-form>
        <input type="hidden" name="csrf_token" value="{$csrf_token}">
        <h2>Eliminare la partita?</h2>
        <p>La partita <strong data-delete-code></strong> e il suo stato verranno rimossi definitivamente.</p>
        <div class="dialog-actions">
            <button class="button button-secondary" type="button" data-delete-cancel>Annulla</button>
            <button class="button button-danger" type="submit">Elimina</button>
        </div>
    </form>
</dialog>
