# Death by AI — progetto di Programmazione Web

Gioco narrativo web per **1-5 giocatori**. A ogni turno l’AI propone un pericolo, i giocatori spiegano come sopravvivere e il giudice decide se ciascuno è salvo oppure perde una vita.

Il progetto è volutamente essenziale: PHP 8, MySQL/MariaDB, Smarty, CSS e JavaScript, con architettura Presentation-Control-Entity-Foundation e Front Controller unico.

## Avvio con XAMPP

1. Aprire il pannello XAMPP e avviare **Apache** e **MySQL**.
2. Importare [`database/schema.sql`](database/schema.sql) da phpMyAdmin. Lo script crea il database `death_by_ai` e due sole tabelle.
3. Se l’utente MySQL `root` ha una password, copiare `config/config.local.example.php` in `config/config.local.php` e inserirla. Il file locale è escluso da Git.
4. Aprire `http://localhost/PROGWEB/`.

Credenziali predefinite XAMPP usate dall’applicazione: host `127.0.0.1`, porta `3306`, utente `root`, password vuota.

## Configurazione AI

Le chiamate partono sempre dal server PHP: chiavi e prompt non vengono esposti al browser.

### OpenAI API

Nel file `config/config.local.php`:

```php
'ai' => [
    'provider' => 'openai',
    'openai_api_key' => 'INSERIRE_LA_CHIAVE_API',
    'openai_model' => 'gpt-4o-mini',
    'max_output_tokens' => 700,
],
```

La chiave è una chiave della **OpenAI API**, non la password né l’abbonamento ChatGPT. `config/config.local.php` è già predisposto ed è escluso da Git. `gpt-4o-mini` è usato per contenere il costo; il limite d’uscita viene inoltre adattato al numero di giocatori.

### Ollama locale

```php
'ai' => [
    'provider' => 'ollama',
    'ollama_url' => 'http://127.0.0.1:11434',
    'ollama_model' => 'llama3.2',
],
```

Per attivarlo basta commentare la configurazione OpenAI e decommentare il blocco Ollama già presente in `config/config.local.php`. Avviare Ollama e scaricare il modello con `ollama pull llama3.2`.

Il provider effettivamente usato è quello indicato da `provider`. Se il servizio AI non risponde durante una dimostrazione, entra automaticamente in funzione un giudice simulato con lo stesso formato JSON. In questo modo il flusso resta presentabile anche senza rete.

### Come viene usata l’AI

La valutazione avviene in due chiamate distinte:

1. `evaluateSurvival()` confronta scenario e risposta e fissa `SAFE` oppure `LOSE_LIFE` con una motivazione breve;
2. `generateStory()` riceve il verdetto già fissato e scrive soltanto il racconto, senza poter cambiare chi perde la vita.

Gli scenari generati vengono limitati anche da PHP a **35 parole e massimo 3 frasi**. I racconti sono limitati a 2-3 frasi per giocatore. La follia non aumenta la lunghezza e non impone una percentuale di sconfitte: modifica soltanto la temperatura usata dal provider.

| Follia | Temperatura scenario/racconto | Temperatura verdetto |
|---|---:|---:|
| 1 | 0.35 | 0.15 |
| 2 | 0.70 | 0.35 |
| 3 | 1.05 | 0.60 |

Il verdetto usa temperature più basse perché deve restare coerente; il racconto può essere più creativo. I limiti di token dipendono soltanto dal numero di giocatori, non dalla follia.

## Perché il database viene aggiornato durante la partita

- `$_SESSION` conserva soltanto l’identità dell’utente, il token CSRF e i messaggi temporanei.
- La partita deve essere condivisa da browser e utenti diversi; quindi non può vivere solo nella RAM o nella sessione dell’host.
- La tabella `games` contiene una riga per partita e il campo `state_json` conserva lobby, vite, scenario, risposte, esiti e cronologia.
- Una modifica del gioco aggiorna quella singola riga dentro una transazione.
- A fine partita la stessa riga rimane come resoconto permanente.

Le due tabelle sono:

- `users`: account e password cifrate con `password_hash()`;
- `games`: configurazione e stato JSON della partita.

Non esistono tabelle separate per partecipanti, turni, risposte o giudizi.

## Struttura essenziale

```text
app/
├── Presentation/VView.php
├── Control/CAuth.php, CGame.php
├── Entity/EUser.php, EGame.php
└── Foundation/FDatabase.php, FSession.php,
               FPersistentManager.php, FAIService.php
```

Sono **9 classi applicative**. I template Smarty si trovano in `templates/`, CSS e JavaScript in `public/`, lo schema in `database/`.

## Funzioni già implementate

- registrazione, login e logout;
- password hash, sessioni, CSRF, prepared statement ed escaping Smarty;
- creazione partita con 1-5 giocatori, 1-3 vite e follia 1-3;
- tema casuale, tema predefinito o tema personalizzato, con un nuovo pericolo generato a ogni turno;
- scenari sempre pericolosi, brevi e coerenti con il tema scelto;
- codice invito e lobby soltanto nelle partite multiplayer;
- single player senza codice invito visibile;
- timer scelto dall’host tra 10 e 60 secondi e invio automatico alla scadenza;
- scenario, risposta modificabile e polling asincrono con Fetch;
- giudizio AI `SAFE`/`LOSE_LIFE` basato su scenario e risposta, senza perdite obbligatorie;
- verdetto e racconto generati in due passaggi separati;
- scenari AI brevi, fino a 35 parole e 3 frasi, indipendentemente dalla follia;
- racconto personalizzato per ogni risposta e lettura text-to-speech del browser;
- eliminazione delle partite non concluse, riservata all’host e con conferma;
- spettatore a zero vite, turni successivi, vincitore o pareggio;
- cronologia conservata in `games.state_json`.

Per provare due utenti sullo stesso computer bisogna usare due browser, due profili oppure una finestra anonima: le normali schede dello stesso browser condividono il cookie di sessione PHP.

Il piano progettuale completo e coerente con le dispense è in [`PIANO_PROGETTO.md`](PIANO_PROGETTO.md).
