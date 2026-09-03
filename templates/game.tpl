<section class="game-shell" data-game-code="{$game->code}" data-game-phase="{$game->phase}" data-game-round="{$game->round}" data-deadline-at="{$game->deadlineAt|default:0}" data-server-time="{$server_time}" data-single-player="{if $game->maxPlayers === 1}1{else}0{/if}" data-confirmation-grace="{$automatic_confirmation_grace}">
    <header class="game-heading">
        <div>
            <span class="eyebrow">{if $game->maxPlayers === 1}Modalità single player{else}Partita {$game->code}{/if}</span>
            <h1>{if $game->status === 'LOBBY'}La lobby è pronta.{elseif $game->status === 'FINISHED'}Partita conclusa.{else}Turno {$game->round}{/if}</h1>
        </div>
        <a class="text-link" href="{$base_url}/">← Dashboard</a>
    </header>

    <div class="game-grid">
        <aside class="panel roster">
            <div class="panel-title"><span>{$game->players|count}/{$game->maxPlayers}</span><h2>Giocatori</h2></div>
            <ul class="player-list">
                {foreach $game->players as $player}
                    <li>
                        <span><strong>{$player.username}</strong>{if $player.user_id === $game->hostUserId}<small>HOST</small>{/if}</span>
                        <span class="life-badge {if $player.lives === 0}life-empty{/if}">{$player.lives} ♥</span>
                    </li>
                {/foreach}
            </ul>
            <dl class="game-rules">
                <div><dt>Vite</dt><dd>{$game->initialLives}</dd></div>
                <div><dt>Timer</dt><dd>{$game->roundDurationSeconds}s</dd></div>
                <div><dt>Modalità</dt><dd>{if $game->maxPlayers === 1}Solo{else}Gruppo{/if}</dd></div>
            </dl>
        </aside>

        <div class="play-column">
            {if $game->status === 'LOBBY'}
                <article class="panel lobby-card">
                    {if $game->maxPlayers === 1}
                        <span class="scenario-label">Partita singola</span>
                        <h2>Sei pronto ad affrontare il primo pericolo?</h2>
                        <p>Non serve alcun codice: la partita inizierà subito con le impostazioni scelte.</p>
                    {else}
                        <span class="scenario-label">Codice invito</span>
                        <div class="invite-code">{$game->code}</div>
                        <p>Condividilo con gli amici. La pagina controlla automaticamente quando qualcuno entra.</p>
                    {/if}
                    {if $is_host}
                        <form method="post" action="{$base_url}/game/{$game->code}/start">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <button class="button button-full" type="submit">{if $game->maxPlayers === 1}Inizia la partita{else}Avvia con {$game->players|count} giocatore/i{/if}</button>
                        </form>
                    {else}
                        <div class="waiting"><span></span> In attesa che l’host inizi...</div>
                    {/if}
                </article>
            {elseif $game->phase === 'OPEN'}
                <article class="panel scenario-card">
                    <div class="preview-topline">
                        <span>Incipit</span>
                        <span class="round-timer" data-round-timer aria-live="polite">--:--</span>
                    </div>
                    <h2>{$game->scenario}</h2>
                </article>

                {if $current_player.lives > 0}
                    {if $current_player.answer}
                        <div class="panel answer-card confirmed-answer">
                            <strong>Risposta confermata</strong>
                            <p>{$current_player.answer}</p>
                            <small>La risposta è definitiva e non può più essere modificata.</small>
                        </div>
                    {else}
                        <form class="panel answer-card" method="post" action="{$base_url}/game/{$game->code}/answer" data-answer-form>
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <label for="answer"><strong>Come continui la storia?</strong></label>
                            <textarea id="answer" name="answer" minlength="3" maxlength="700" rows="5" required></textarea>
                            <button class="button" type="submit">Conferma risposta</button>
                            <small>Allo scadere del timer, il testo presente verrà confermato automaticamente.</small>
                        </form>
                    {/if}
                {else}
                    <div class="panel spectator-card"><strong>Sei senza vite.</strong><p>Puoi continuare a seguire la partita come spettatore.</p></div>
                {/if}

                <div class="waiting"><span></span> {if $game->maxPlayers === 1}La risposta verrà valutata appena la confermi.{else}Il verdetto partirà automaticamente allo scadere del timer.{/if}</div>
                <form method="post" action="{$base_url}/game/{$game->code}/evaluate" data-auto-evaluate hidden>
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                </form>
            {elseif $game->phase === 'EVALUATING'}
                <article class="panel evaluating-card">
                    <div class="danger-orbit" aria-hidden="true"><span class="danger-core">AI</span></div>
                    <span class="eyebrow">Tempo scaduto</span>
                    <h2>Il destino sta scrivendo il verdetto...</h2>
                    <p>Le risposte sono state bloccate e inviate al narratore.</p>
                </article>
            {elseif $game->phase === 'RESULTS' || ($game->status === 'FINISHED' && $game->lastResults)}
                <article class="panel verdict-card">
                    <div class="verdict-topline">
                        <span class="eyebrow">Verdetto del turno {$game->round}</span>
                        <button class="speak-button" type="button" data-speak-verdict>🔊 Ascolta il racconto</button>
                    </div>
                    <ul class="result-list" data-verdict-text>
                        {foreach $game->lastResults as $result}
                            <li class="result-{if $result.outcome === 'SAFE'}safe{else}danger{/if}">
                                <div><strong>{$result.username}</strong><span>{if $result.outcome === 'SAFE'}SOPRAVVIVE{else}MUORE · −1 VITA{/if}</span></div>
                                <p>{$result.story}</p>
                                <small>Vite rimaste: {$result.lives}</small>
                            </li>
                        {/foreach}
                    </ul>
                    <p class="ai-source">
                        Giudice: {if $game->lastJudgmentSource === 'openai'}OpenAI{elseif $game->lastJudgmentSource === 'ollama'}Ollama locale{else}fallback{/if}
                        · Narrazione: {if $game->lastStorySource === 'openai'}OpenAI{elseif $game->lastStorySource === 'ollama'}Ollama locale{else}fallback{/if}
                    </p>
                    {if $game->status === 'FINISHED'}
                        <div class="final-summary">
                            {if $game->winnerUsername}
                                <strong>{$game->winnerUsername} è il vincitore.</strong>
                            {else}
                                <strong>La partita termina senza superstiti.</strong>
                            {/if}
                        </div>
                        <a class="button button-full" href="{$base_url}/">Torna alla dashboard</a>
                    {elseif $is_host}
                        <form method="post" action="{$base_url}/game/{$game->code}/next">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <button class="button button-full" type="submit">Genera il prossimo turno</button>
                        </form>
                    {else}<div class="waiting"><span></span> In attesa del prossimo turno...</div>{/if}
                </article>
            {else}
                <article class="panel finish-card">
                    <span class="eyebrow">Fine della storia</span>
                    {if $game->winnerUsername}
                        <h2>{$game->winnerUsername} è sopravvissuto.</h2>
                    {else}
                        <h2>Nessuno è sopravvissuto.</h2>
                    {/if}
                    <p>Turni giocati: {$game->roundsPlayed}. La cronologia completa resta salvata nel database.</p>
                    <a class="button" href="{$base_url}/">Crea un’altra partita</a>
                </article>
            {/if}
        </div>
    </div>
</section>
<script src="{$base_url}/public/js/game.js" defer></script>
