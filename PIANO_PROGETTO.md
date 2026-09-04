# Piano essenziale del progetto - Death by AI

## 1. Obiettivo


Un utente registrato può creare una partita da solo oppure invitare fino a quattro amici tramite un codice. A ogni turno viene mostrato uno scenario pericoloso e ogni giocatore ancora in gioco scrive come pensa di sopravvivere. Un servizio AI valuta le risposte e decide, per ogni giocatore, se è salvo oppure perde una vita.

Il progetto deve dimostrare gli argomenti del corso senza trasformarsi in un'applicazione troppo grande:

- PHP orientato agli oggetti;
- MySQL tramite PDO;
- HTML e Smarty;
- CSS;
- JavaScript e manipolazione del DOM;
- sessioni PHP;
- Front Controller con `index.php` e `.htaccess`;
- chiamata a un Web Service AI;
- architettura Presentation, Control, Entity e Foundation.

## 2. Regole definitive del gioco

- Una partita può avere da **1 a 5 giocatori**.
- L'utente che crea la partita è l'host.
- L'host sceglie le vite iniziali, da **1 a 3**.
- Le temperature dell'AI sono predefinite nel servizio e non sono configurabili dai giocatori.
- Il servizio alterna cinque macro-categorie interne e l'AI inventa autonomamente contenuto e formulazione di ogni incipit.
- In modalità single player la partita può iniziare immediatamente e non viene mostrato alcun codice invito.
- In multiplayer gli amici entrano con un codice e l'host avvia la partita.
- A ogni turno l'AI genera un incipit diverso dai precedenti che il giocatore deve continuare.
- Ogni incipit contiene una minaccia immediata e potenzialmente mortale e termina con `Cosa fai?`.
- Ogni giocatore con almeno una vita invia una risposta per il turno.
- L'host sceglie una durata tra 10 e 60 secondi. Alla scadenza le risposte vengono bloccate e inviate automaticamente al giudice AI; se tutti hanno risposto, l'host può anticipare il verdetto.
- Per ogni giocatore l'AI restituisce uno dei due esiti:
  - `SAFE`: non perde vite;
  - `LOSE_LIFE`: perde una vita.
- In un turno possono perdere una vita tutti, alcuni oppure nessuno.
- Non esiste l'obbligo di eliminare qualcuno a ogni turno.
- Un giocatore con zero vite non può più rispondere, ma può continuare a vedere la partita.
- In multiplayer la partita termina quando:
  - rimane un solo giocatore con vite, che è il vincitore;
  - tutti arrivano a zero vite nello stesso turno, producendo un pareggio.
- In single player la partita termina quando il giocatore arriva a zero vite.
- Chi non risponde entro la deadline perde una vita. La deadline ufficiale è salvata nel JSON e calcolata dal server.

## 3. Funzionalità da realizzare

### Obbligatorie

1. Registrazione.
2. Login e logout.
3. Pagina iniziale per creare una partita o inserire un codice.
4. Creazione partita con:
   - numero massimo di giocatori da 1 a 5;
   - vite iniziali da 1 a 3;
   - durata del turno da 10 a 60 secondi.
5. Generazione del codice invito soltanto per il multiplayer.
6. Ingresso degli amici tramite codice.
7. Lobby minimale con nomi dei partecipanti.
8. Avvio della partita.
9. Generazione dello scenario tramite AI.
10. Invio della risposta.
11. Aggiornamento asincrono dello stato con JavaScript.
12. Valutazione delle risposte tramite AI.
13. Perdita delle vite decisa dall'AI.
14. Turno successivo.
15. Fine partita e visualizzazione del resoconto.

### Da non realizzare nella prima versione

- pannello amministratore;
- amicizie persistenti;
- chat;
- email;
- notifiche;
- avatar;
- classifiche globali;
- matchmaking;
- partite pubbliche;
- WebSocket;
- caricamento di immagini;
- più modalità di gioco.

Se il progetto obbligatorio funziona bene, una di queste funzionalità potrà essere aggiunta in seguito. Non fa parte del piano iniziale.

## 4. Attori e obiettivi - Step 1

### Visitatore

- registrarsi;
- effettuare il login.

### Giocatore

- creare una partita;
- giocare in single player;
- entrare nella partita di un amico;
- leggere lo scenario;
- inviare la risposta;
- vedere quante vite possiede;
- leggere la decisione dell'AI;
- vedere il risultato finale.

### Host

L'host è anche un giocatore e può inoltre:

- scegliere le impostazioni;
- condividere il codice;
- avviare la partita;
- chiudere un turno quando necessario.

### Servizio AI

- generare autonomamente un incipit di vita o di morte;
- valutare ogni risposta;
- restituire un risultato JSON controllabile da PHP.

## 5. Casi d'uso - Step 2

Per non produrre troppa documentazione, saranno descritti sei casi d'uso.

1. **UC1 - Registrarsi e autenticarsi**
2. **UC2 - Creare e configurare una partita**
3. **UC3 - Entrare in una partita con il codice**
4. **UC4 - Avviare e giocare un turno**
5. **UC5 - Valutare le risposte e aggiornare le vite**
6. **UC6 - Terminare la partita e mostrare il resoconto**

### Caso d'uso principale: UC4 - Avviare e giocare un turno

**Attore:** giocatore.

**Precondizioni:**

- l'utente ha effettuato il login;
- appartiene alla partita;
- la partita è iniziata;
- possiede almeno una vita;
- il turno è aperto.

**Scenario principale:**

1. Il giocatore apre la pagina della partita.
2. Il sistema mostra l'incipit, il timer e le vite dei giocatori.
3. Il giocatore continua la storia descrivendo la propria azione.
4. JavaScript controlla che la risposta non sia vuota e non superi il limite.
5. Il giocatore invia la risposta.
6. PHP ripete la validazione e salva la risposta nello stato condiviso della partita.
7. Il sistema conferma l'invio e rende la risposta definitiva.
8. In single player parte immediatamente la valutazione; in multiplayer la pagina continua a controllare lo stato del turno tramite richieste `fetch`.

**Alternative:**

- risposta non valida: viene mostrato un messaggio vicino al campo;
- sessione scaduta: viene richiesto nuovamente il login;
- turno già chiuso: la risposta viene rifiutata;
- timer scaduto prima dell'invio: il testo presente viene confermato automaticamente;
- risposta già confermata: ogni tentativo di modifica viene rifiutato;
- giocatore senza vite: la form non viene mostrata;
- errore di rete: il testo rimane visibile e l'utente può riprovare.

**Postcondizione:** nello stato condiviso della partita esiste una sola risposta per quel giocatore e quel turno.

Per ogni altro caso d'uso bastano:

- attore;
- precondizioni;
- scenario principale;
- uno o due errori significativi;
- postcondizioni.

## 6. Schermate - Step 3

Sono sufficienti sei schermate principali:

1. **Login e registrazione**
2. **Home utente** - crea partita oppure inserisci codice
3. **Configurazione partita** - giocatori, vite e durata del turno
4. **Lobby** - codice e partecipanti
5. **Partita** - scenario, vite, risposta e stato del turno
6. **Risultato** - esito del turno oppure fine partita

La pagina Partita può cambiare contenuto senza creare molte pagine differenti:

- turno aperto;
- risposta inviata;
- attesa degli altri;
- valutazione in corso;
- risultato del turno;
- giocatore senza vite;
- partita terminata.

### Regole grafiche

- un solo pulsante principale per schermata;
- layout leggibile sia su computer sia su telefono;
- colori coerenti;
- vite mostrate con icone o numeri chiari;
- errori vicino ai campi;
- indicatore quando l'AI sta elaborando;
- nessuna animazione complessa necessaria.

## 7. Modello di dominio - Step 4

Il modello di dominio può essere limitato a sei concetti:

- Utente;
- Partita;
- Giocatore della partita;
- Turno;
- Scenario;
- Risposta.

Relazioni principali:

- un Utente crea una Partita;
- una Partita comprende da uno a cinque giocatori;
- una Partita contiene più turni;
- ogni Turno possiede uno Scenario;
- ogni giocatore ancora attivo può fornire una Risposta per turno.

La valutazione dell'AI può essere rappresentata come dato del turno e non richiede obbligatoriamente una classe separata.

## 8. Architettura software essenziale

Si mantengono i quattro livelli indicati nel corso, ma con poche classi.

### Presentation

Una sola classe:

- `VView.php`: configura Smarty, mostra un template oppure restituisce JSON.

### Control

Due classi:

- `CAuth.php`: registrazione, login e logout;
- `CGame.php`: creazione, ingresso, avvio, risposta, valutazione e visualizzazione della partita.

La strategia del professore suggerisce una classe Control per caso d'uso. In questo progetto i casi d'uso strettamente collegati vengono raggruppati in due macro-funzionalità coerenti per evitare classi quasi vuote. Ogni operazione dell'utente rimane comunque un metodo separato.

Metodi principali di `CGame`:

- `create()`;
- `join()`;
- `show()`;
- `start()`;
- `answer()`;
- `evaluate()`;
- `nextRound()`;
- `state()`.

### Entity

Due classi:

- `EUser.php`;
- `EGame.php`.

`EGame` contiene la logica principale:

- aggiungere un giocatore;
- iniziare la partita;
- memorizzare una risposta;
- controllare se tutti hanno risposto;
- applicare gli esiti dell'AI;
- diminuire le vite;
- controllare se la partita è finita;
- creare il turno successivo.

### Foundation

Quattro classi:

- `FDatabase.php`: crea la connessione PDO;
- `FSession.php`: gestisce la sessione dell'utente;
- `FPersistentManager.php`: legge e salva utenti e partite;
- `FAIService.php`: chiama Ollama oppure OpenAI.

Il progetto contiene quindi **nove classi applicative**, non decine di classi.

## 9. Struttura delle cartelle

Il progetto si trova direttamente in `C:\xampp\htdocs\PROGWEB`.

```text
PROGWEB/
|-- .htaccess
|-- .gitignore
|-- index.php
|-- autoload.php
|-- README.md
|-- PIANO_PROGETTO.md
|
|-- config/
|   |-- config.php
|   |-- config.local.example.php
|   `-- config.local.php          # non va su Git
|
|-- app/
|   |-- Presentation/
|   |   `-- VView.php
|   |-- Control/
|   |   |-- CAuth.php
|   |   `-- CGame.php
|   |-- Entity/
|   |   |-- EUser.php
|   |   `-- EGame.php
|   `-- Foundation/
|       |-- FDatabase.php
|       |-- FSession.php
|       |-- FPersistentManager.php
|       `-- FAIService.php
|
|-- templates/
|   |-- layout.tpl
|   |-- auth.tpl
|   |-- home.tpl
|   |-- dashboard.tpl
|   |-- game.tpl
|   `-- error.tpl
|
|-- public/
|   |-- css/
|   |   `-- style.css
|   `-- js/
|       |-- app.js
|       `-- game.js
|
|-- database/
|   `-- schema.sql
|
|-- docs/
|   |-- 01_idea_e_casi_uso.pdf
|   |-- 02_wireframe.pdf
|   |-- 03_modello_e_architettura.pdf
|   `-- 04_test.pdf
|
`-- lib/
    `-- smarty/                    # libreria Smarty del corso
```

## 10. Database: cosa deve contenere davvero

### Chiarimento importante

Il database non serve soltanto per dati statici o per il log finale. Serve per dati che devono sopravvivere alla fine di una richiesta PHP.

Ogni richiesta HTTP avvia PHP, esegue il codice e termina. Alla richiesta successiva gli oggetti PHP creati in memoria non esistono più.

La sessione permette di ricordare informazioni del singolo browser, per esempio:

- ID dell'utente autenticato;
- codice dell'ultima partita aperta;
- messaggi temporanei.

La sessione di un giocatore, però, non è visibile agli altri giocatori. Non può quindi essere l'unico posto in cui conservare una partita multiplayer.

Lo stato della partita deve essere condiviso e persistente. Per questo viene salvato nel database, su tabelle relazionali normalizzate invece che in un'unica colonna JSON. È normale aggiornare una o più righe quando:

- entra un giocatore;
- viene inviata una risposta;
- termina un turno;
- cambia il numero di vite;
- inizia il turno successivo.

Questa non è una violazione delle slide: è precisamente il significato dello strato Foundation/Persistence.

### Cinque tabelle relazionali

#### Tabella `users`

- `id`;
- `username`;
- `email`;
- `password_hash`;
- `created_at`.

#### Tabella `games`

Una riga per partita, con configurazione, stato generale e risultato finale:

- `id`;
- `code` - codice breve univoco;
- `host_user_id`;
- `status` - `LOBBY`, `ACTIVE` oppure `FINISHED`;
- `max_players` - da 1 a 5;
- `initial_lives` - da 1 a 3;
- `round_duration_seconds`;
- `rounds_played` - numero di turni già chiusi;
- `winner_user_id`;
- `created_at`, `updated_at`, `finished_at`.

#### Tabella `game_players`

Un giocatore per riga per partita:

- `id`;
- `game_id`;
- `user_id`;
- `lives`;
- `joined_at`.

#### Tabella `rounds`

Un turno per partita, creato appena viene mostrato lo scenario:

- `id`;
- `game_id`;
- `round_number`;
- `status` - `OPEN`, `EVALUATING`, `COMPLETED` oppure `CANCELLED`;
- `scenario`;
- `deadline_at`;
- `started_at`, `completed_at`.

#### Tabella `round_results`

Risposta e successivo esito individuale di un giocatore nello specifico turno:

- `id`;
- `round_id`;
- `game_player_id`;
- `answer`;
- `answered_at`;
- `outcome` - `SAFE` oppure `LOSE_LIFE`;
- `story`;
- `lives_after`.

### Aggiornamento sicuro della partita

Per evitare che due richieste modifichino contemporaneamente lo stato:

1. `FPersistentManager::mutateGame()` apre una transazione;
2. carica la riga di `games` e le righe di `game_players` della partita con blocco `FOR UPDATE`;
3. ricostruisce l'oggetto `EGame` dalle righe caricate;
4. applica una sola operazione (per esempio `submitAnswer`, `applyResults`, `nextRound`);
5. salva le tabelle coinvolte: `games` sempre, `game_players` per vite e risposte, `rounds`/`round_results` soltanto quando un turno è appena stato valutato;
6. esegue il commit.

È un meccanismo breve da implementare e facile da spiegare al professore, e mostra concretamente più concetti relazionali (FK, transazioni, `FOR UPDATE`, cascata) rispetto a un'unica colonna JSON.

## 11. Sessione PHP

La sessione conserva solo:

```text
user_id
username
current_game_code
flash_message
```

Non conserva l'intera partita. In single player si potrebbe tecnicamente fare, ma usare lo stesso meccanismo del multiplayer mantiene il codice più semplice: `EGame` funziona sempre nello stesso modo, sia con uno sia con cinque giocatori.

## 12. AI: Ollama oppure OpenAI

Si usa una sola classe `FAIService`. Il provider viene scelto nel file locale di configurazione:

```php
'ai_provider' => 'ollama', // oppure 'openai'
```

### Ollama

- gira sul computer;
- viene chiamato da PHP su `http://localhost:11434/api/chat`;
- non richiede una chiave;
- il nome del modello viene impostato in `config.local.php`;
- è la scelta consigliata per la demo offline, se il computer è abbastanza potente.

### OpenAI API

- richiede connessione Internet;
- richiede una chiave API della piattaforma OpenAI;
- la chiave API è diversa dall'abbonamento ChatGPT;
- la chiave rimane soltanto in `config.local.php` e non viene inviata al browser;
- PHP effettua la richiesta HTTP con cURL.

`FAIService` espone tre operazioni semplici:

- `createScenario(array $previousScenarios, int $roundNumber): string` genera un incipit completo e di lunghezza uniforme, evitando gli scenari recenti passati esplicitamente da `CGame` (letti da `FPersistentManager::recentScenarios()`);
- `evaluateSurvival(EGame $game): array` decide soltanto `SAFE` o `LOSE_LIFE`;
- `generateStory(EGame $game, array $evaluation): array` racconta il verdetto già deciso senza modificarlo.

Il resto dell'applicazione non deve sapere quale provider è attivo.

### Tre risposte richieste all'AI

La generazione dell'incipit accetta più formati, così il prompt può essere modificato liberamente durante i test:

```json
{
  "scenario": "Testo generato secondo il prompt corrente."
}
```

Sono supportati testo semplice, stringhe JSON, l'oggetto `scenario` e il precedente oggetto `setup`/`danger`. PHP verifica soltanto che il risultato non sia vuoto, normalizza gli spazi e aggiunge `Cosa fai?` quando manca. Non impone limiti di parole o frasi e non tronca il testo.

La seconda chiamata valuta incipit e continuazione:

```json
{
  "results": [
    {"player_id": 7, "outcome": "SAFE"}
  ]
}
```

La terza chiamata riceve questi verdetti come dati definitivi e genera soltanto il racconto:

Per ogni giocatore l'AI deve restituire JSON in questo formato:

```json
{
  "results": [
    {"player_id": 7, "story": "Narrazione individuale di 5 frasi su come Anna sopravvive."},
    {"player_id": 12, "story": "Narrazione individuale di 5 frasi su come Luca perde una vita."}
  ]
}
```

PHP accetta la risposta solo se:

- il JSON è valido;
- ogni giocatore atteso compare una sola volta;
- non compaiono giocatori inventati;
- l'esito è `SAFE` oppure `LOSE_LIFE`;
- ogni racconto individuale richiesto è presente.

Sono validi anche risultati in cui tutti sono `SAFE` oppure tutti sono `LOSE_LIFE`. Le temperature del modello sono costanti interne a `FAIService` e non impostazioni di gioco. Forma e lunghezza dell'incipit sono controllate dal prompt corrente.

Se una risposta dell'AI non è valida o il servizio non risponde, entra in funzione il fallback locale con lo stesso formato previsto.

## 13. Front Controller e URL - Step 7

Tutte le richieste dinamiche arrivano a `index.php`. I file CSS e JavaScript esistenti vengono serviti direttamente da Apache.

URL minime:

| Metodo | URL | Metodo Control |
|---|---|---|
| GET | `/` | `CGame::home()` |
| GET/POST | `/register` | `CAuth::register()` |
| GET/POST | `/login` | `CAuth::login()` |
| POST | `/logout` | `CAuth::logout()` |
| GET/POST | `/game/create` | `CGame::create()` |
| POST | `/game/join` | `CGame::join()` |
| GET | `/game/{code}` | `CGame::show()` |
| POST | `/game/{code}/start` | `CGame::start()` |
| POST | `/game/{code}/answer` | `CGame::submitAnswer()` |
| POST | `/game/{code}/evaluate` | `CGame::evaluateRound()` |
| GET | `/api/game/{code}` | `CGame::getState()` |

Queste URL sono sufficienti per dimostrare Front Controller, metodi HTTP e risorse autodescrittive.

## 14. JavaScript asincrono

Non si usano WebSocket.

`public/js/game.js` esegue ogni pochi secondi:

```text
GET /api/game/{code}
```

Il server restituisce soltanto:

- stato della partita;
- numero del turno;
- scenario corrente;
- nomi e vite dei giocatori;
- giocatori che hanno già risposto;
- ultimo risultato disponibile.

JavaScript aggiorna il DOM senza ricaricare tutta la pagina. Le regole importanti vengono comunque controllate da PHP.

## 15. Sicurezza minima necessaria

- password con `password_hash()` e `password_verify()`;
- PDO con prepared statement;
- validazione in JavaScript e ripetuta in PHP;
- escaping dell'output nei template Smarty;
- rigenerazione dell'ID di sessione dopo il login;
- controllo che l'utente appartenga alla partita;
- controllo che solo l'host possa avviare e che la valutazione multiplayer parta soltanto dopo il timer;
- limite di lunghezza per le continuazioni dei giocatori;
- chiave OpenAI esclusa da Git;
- nessuna chiamata AI direttamente dal browser.

## 16. Documentazione da mostrare al professore

Quattro documenti brevi sono sufficienti:

1. `01_idea_e_casi_uso.pdf`
   - obiettivo;
   - attori;
   - sei casi d'uso;
   - Use Case Diagram.
2. `02_wireframe.pdf`
   - sei schermate;
   - flusso del turno;
   - stato di errore e caricamento.
3. `03_modello_e_architettura.pdf`
   - modello di dominio;
   - diagramma delle nove classi;
   - livelli PCEF;
   - le cinque tabelle relazionali;
   - URL principali.
4. `04_test.pdf`
   - test del percorso principale;
   - errori più importanti;
   - prova single player;
   - prova con due o più browser.

## 17. Sequenza di sviluppo

### Fase 1 - Progettazione

- approvare le regole del gioco;
- scrivere i sei casi d'uso;
- disegnare le sei schermate;
- creare modello di dominio e diagramma delle classi.

### Fase 2 - Base del sito

- configurare XAMPP e MySQL;
- creare Front Controller;
- configurare Smarty;
- realizzare registrazione, login e logout.

### Fase 3 - Gioco senza AI

- creare e raggiungere una partita con il codice;
- aggiungere giocatori allo stato JSON;
- avviare la partita;
- usare scenari e valutazioni fittizie;
- completare un'intera partita.

Questa fase deve già produrre un gioco funzionante.

### Fase 4 - AI

- integrare prima Ollama oppure OpenAI;
- validare il JSON restituito;
- configurare il secondo provider solo dopo che il primo funziona.

### Fase 5 - Presentazione e test

- completare CSS e responsive design;
- provare single player;
- provare multiplayer con più browser;
- provare tutti salvi, tutti colpiti e pareggio;
- provare errore AI;
- completare i quattro PDF.

## 18. Cosa mostrare durante l'esame

Una demo breve:

1. mostrare le cartelle PCEF;
2. mostrare le cinque tabelle MySQL;
3. effettuare il login;
4. creare una partita con vite e durata del turno;
5. entrare con un secondo browser oppure avviare in single player;
6. inviare le risposte;
7. mostrare la chiamata AI e il JSON ricevuto;
8. mostrare la perdita di una vita;
9. mostrare le righe di `games`, `game_players` e `round_results` aggiornate;
10. mostrare il risultato finale e il log dei turni in `rounds`;
11. collegare un'operazione alla relativa URL, metodo Control, metodo Entity e persistenza.

## 19. Checklist finale

- [ ] Da 1 a 5 giocatori.
- [ ] Da 1 a 3 vite scelte dall'host.
- [ ] Nuovi scenari generati autonomamente dall'AI a ogni turno.
- [ ] Temperature AI predefinite e non modificabili dai giocatori.
- [ ] Nessun obbligo di perdere una vita a ogni turno.
- [ ] Single player funzionante.
- [ ] Invito multiplayer tramite codice.
- [ ] Database MySQL normalizzato su cinque tabelle relazionali (`users`, `games`, `game_players`, `rounds`, `round_results`).
- [ ] Stato condiviso nelle tabelle di partita, aggiornato dentro transazioni con blocco di riga.
- [ ] Nove classi applicative.
- [ ] Presentation, Control, Entity e Foundation riconoscibili.
- [ ] Sessione limitata ai dati del singolo utente.
- [ ] `index.php` come Front Controller.
- [ ] Smarty, CSS e JavaScript separati.
- [ ] Ollama oppure OpenAI configurabile.
- [ ] JSON dell'AI validato prima di modificare le vite.
- [ ] Gioco completo funzionante anche con risposte AI simulate durante lo sviluppo.
- [ ] Quattro documenti brevi per la discussione.

## Decisione progettuale principale

La semplicità del progetto non consiste nel tenere lo stato della partita soltanto in memoria o nelle sessioni. Questo non funzionerebbe correttamente tra più utenti e più richieste HTTP.

La soluzione semplice e corretta è:

```text
sessione PHP = identità del singolo utente
database users = account permanenti
database games / game_players / rounds / round_results = stato condiviso della partita
Entity EGame = regole e logica di gioco
```

In questo modo il progetto rimane piccolo, ma dimostra correttamente sessioni, persistenza, OOP, MySQL, JSON, JavaScript asincrono e Web Service.
