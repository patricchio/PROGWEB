# Documentazione Progetto PROGWEB

Questa è la documentazione completa e dettagliata del progetto PROGWEB. Il codice viene analizzato e spiegato riga per riga per facilitarne la comprensione anche a scopo didattico.

---

## 1. File Principali

### index.php
Questo è il Front Controller dell'applicazione. È il punto di ingresso per tutte le richieste web.

#### Dettaglio del codice:
- equire_once __DIR__ . '/autoload.php';
  Include l'autoloader. L'autoloader si occupa di caricare automaticamente le classi quando vengono istanziate, senza dover includere manualmente ogni file.
- FSession::start();
  Avvia la sessione dell'utente chiamando il metodo statico start() della classe FSession. Questo è fondamentale per mantenere lo stato dell'utente (es. se è loggato) attraverso le varie pagine.
- $view = new VView(__DIR__);
  Istanzia la classe per la vista (VView), passandole la directory root del progetto. Questo oggetto verrà usato dai controller per renderizzare le pagine o inviare risposte JSON.
- $requestMethod = strtoupper(['REQUEST_METHOD'] ?? 'GET');
  Recupera il metodo della richiesta HTTP (es. GET, POST). strtoupper assicura che sia tutto in maiuscolo.
- $requestPath = parse_url(['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  Estrae il percorso (path) dall'URL richiesto, ignorando eventuali parametri in query string (?chiave=valore).
- $basePath = rtrim(str_replace('\\', '/', dirname(['SCRIPT_NAME'] ?? '')), '/');
  Calcola il percorso base dell'applicazione. Questo serve nel caso in cui l'applicazione non sia installata nella root del server (es. localhost/PROGWEB al posto di localhost/).
- Le righe da 13 a 15 gestiscono la normalizzazione del path: se l'URI richiesto inizia con il basePath, questo viene rimosso dal $requestPath per semplificare il pattern matching successivo nelle rotte.
- $routes = [...]
  Definisce un array di rotte. Ogni rotta è a sua volta un array contenente:
  1. Il metodo HTTP (GET o POST).
  2. Un'espressione regolare (regex) per confrontare il $requestPath.
  3. La classe del Controller da usare.
  4. Il nome del metodo del Controller da invocare.
- Il ciclo oreach ( as ...) scorre tutte le rotte per trovare una corrispondenza:
  - preg_match(, , ) esegue il match. Se trova la corrispondenza, estrae anche eventuali parametri definiti nella regex tra parentesi tonde.
  - rray_shift(); rimuove il primo elemento dell'array $matches (che è la stringa intera che ha fatto match), lasciando solo i parametri estratti.
  - $controller = new (, ); istanzia dinamicamente il controller corretto.
  - $params = array_values(); ottiene i parametri purificati.
  - call_user_func_array([, ], ); invoca il metodo del controller passandogli i parametri estratti dall'URL.
  - exit; interrompe l'esecuzione dopo aver gestito la rotta.
- Se nessuna rotta fa match, lo script prosegue.
- http_response_code(404); imposta lo status code HTTP a 404 (Not Found).
- Infine, chiama $view->render('error.tpl', [...]) per mostrare una pagina di errore generica all'utente.

---

## 2. Cartella pp/Presentation

### Classe VView (File: pp/Presentation/VView.php)
Questa classe si occupa del livello di Presentazione. Usa il motore di template Smarty per renderizzare l'HTML o restituisce direttamente JSON.

#### Metodi:

**__construct(string )**
- Viene richiamata all'istanziazione. Includendo prima di tutto Smarty.class.php.
- Calcola $compileDirectory che è la cartella dove Smarty salverà i template compilati (storage/smarty-compile).
- Se la cartella non esiste (!is_dir(...)), la crea con mkdir(..., 0775, true). Questo evita errori a runtime per directory mancanti.
- Istanzia un nuovo oggetto Smarty, imposta la directory dove si trovano i template .tpl (	emplates) e la directory per la compilazione.

**ender(string , array  = []): void**
- Riceve in input il nome del template specifico da mostrare e i dati associati.
- $this->smarty->assign();: passa le variabili del controller al template.
- $this->smarty->assign('current_user', FSession::user());: rende automaticamente disponibile in tutti i template l'utente loggato.
- $this->smarty->assign('flash', FSession::consumeFlash());: preleva (e consuma) un eventuale messaggio di notifica "flash" salvato in sessione (es. messaggi di errore o successo temporanei).
- $this->smarty->assign('page_template', );: passa il nome del template da includere internamente.
- $this->smarty->display('layout.tpl');: mostra layout.tpl, che funge da contenitore principale (header, footer, menu) e che a sua volta includerà il $page_template.

**json(array , int  = 200): void**
- Metodo usato per rispondere alle richieste AJAX/API.
- http_response_code();: imposta lo stato HTTP (default 200).
- header('Content-Type: application/json; charset=utf-8');: comunica al browser che il contenuto che sta arrivando è di tipo JSON puro.
- echo json_encode(, JSON_UNESCAPED_UNICODE);: trasforma l'array PHP in una stringa JSON senza fare l'escaping dei caratteri Unicode (utile per accenti e caratteri speciali) e lo invia al client.

---

## 3. Cartella pp/Entity

### Classe EUser (File: pp/Entity/EUser.php)
È l'Entità che rappresenta un Utente del sistema. Modella i dati puri senza logica complessa.

#### Metodi:

**__construct(, , , ,  = false)**
- Il costruttore standard. Prende in input tutti i campi fondamentali per descrivere l'utente nel sistema e li mappa sulle proprietà pubbliche dell'oggetto (es. $this->id = ;). Imposta l'utente come amministratore opzionalmente ($isAdmin).

**romRow() (statico)**
- Un costruttore di supporto o "factory method". Prende una riga grezza prelevata dal database (un array associativo, ad es. restituito da PDO) e la converte in un oggetto EUser.
- Usa i cast ((int), (string), (bool)) per forzare il tipo di dato corretto assicurando integrità ai valori.

---

## 4. Cartella pp/Foundation (Parte 1)

### Classe FDatabase (File: pp/Foundation/FDatabase.php)
Gestisce la connessione al database usando il pattern Singleton per non aprire mille connessioni inutili.

#### Metodi:

**connection(): PDO (statico)**
- Controlla se la proprietà statica $connection contiene già un'istanza di PDO. Se sì, la restituisce subito.
- Se no, legge la configurazione dal file config/config.php.
- Crea la stringa DSN (mysql:host=...;dbname=...) usata da PDO per connettersi al DBMS.
- Istanziato un nuovo oggetto PDO, passa array con due attributi chiave:
  - PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION: fa lanciare un'eccezione ogni volta che una query fallisce (ottimo per il debugging e la gestione errori).
  - PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC: imposta PDO in modo che ritorni i risultati sempre sotto forma di array associativi anziché array con chiavi numeriche e associative duplicate.
- Infine, salva questa istanza in $connection e la restituisce.

### Classe FSession (File: pp/Foundation/FSession.php)
Raggruppa tutta la logica per la gestione delle sessioni PHP.

#### Metodi:

**start(): void (statico)**
- Controlla con session_status() se una sessione è già attiva; se non lo è (PHP_SESSION_NONE), chiama session_start().

**login(EUser ): void (statico)**
- Viene chiamato quando l'utente immette credenziali valide. Salva un array sintetico con id, username, email e privilegi nell'array superglobale $_SESSION['user'].
- Salviamo solo dati essenziali (mai la password hashata) in sessione per mantenere il carico leggero e la sicurezza intatta.

**logout(): void (statico)**
- Svuota l'array $_SESSION.
- Invoca session_destroy() per rimuovere il file di sessione dal server.

**user(): ?array (statico)**
- Restituisce i dati dell'utente loggato, altrimenti 
ull.

**equireUser(string ): array (statico)**
- Metodo usato dai controller per proteggere le pagine private.
- Chiama self::user(). Se è null, l'utente non è autenticato.
- Se null, imposta un messaggio flash d'errore ("Accedi per continuare"), reindirizza l'utente alla pagina /login usando header('Location: ...') e blocca subito l'esecuzione dello script tramite exit.
- Se è loggato, restituisce i dati dell'utente al controller per poterli utilizzare.

**lash(string , string ): void (statico)**
- Memorizza in sessione un messaggio (con il suo tipo, es. 'error', 'success'). Viene usato tipicamente subito prima di un redirect per mostrare il messaggio nella pagina successiva.

**consumeFlash(): ?array (statico)**
- Legge il messaggio flash (se presente) salvandolo in una variabile locale.
- Cancella subito dopo il messaggio dalla sessione tramite unset(['flash']).
- Restituisce il messaggio per farlo visualizzare al View, garantendo che venga mostrato solo per una volta (comportamento "flash").

---

## 5. Cartella pp/Entity (Parte 2)

### Classe EGame (File: pp/Entity/EGame.php)
Rappresenta una Partita e incapsula gran parte della logica di dominio (regole del gioco, gestione turni, calcolo vite).

#### Metodi principali:

**__construct(...)**
- Inizializza l'oggetto con tutti i campi di una partita (id, codice, max_players, stato, vite iniziali, ecc.).

**create(...) (statico)**
- Factory method usato quando un utente crea una nuova partita.
- Restituisce una nuova istanza in stato 'LOBBY' (fase in cui gli utenti si possono unire).
- Inserisce automaticamente l'utente creatore (host) nell'array dei $players.

**romRow(...) e romSummaryRow(...) (statici)**
- Costruiscono l'oggetto EGame a partire dai dati provenienti dal database. romSummaryRow crea una versione leggera (solo conteggio giocatori) usata ad esempio per la dashboard, risparmiando memoria.

**ddPlayer(array ): void**
- Controlla se la partita è in stato 'LOBBY'. Se non lo è, lancia un'eccezione (DomainException).
- Controlla se il numero di giocatori attuali ha raggiunto il massimo consentito ($this->maxPlayers). Se sì, lancia eccezione.
- Se c'è posto, aggiunge l'utente all'array dei giocatori assegnandogli le vite iniziali.

**start(string ): void**
- Sposta lo stato della partita da 'LOBBY' ad 'ACTIVE' e la fase a 'OPEN'.
- Imposta il turno (round) a 1.
- Salva lo scenario testuale generato (presumibilmente da un'IA).
- Imposta la scadenza del turno ($this->deadlineAt) sommando il tempo attuale con i secondi del turno.

**submitAnswer(int , string , bool  = false): void**
- Registra l'azione che l'utente vuole intraprendere in questo round.
- Verifica che il turno sia aperto ('OPEN'), che l'utente sia in partita e abbia vite maggiori di zero.
- Controlla che il tempo non sia scaduto. Se è scaduto e l'invio non è automatico (triggerato dal frontend alla scadenza del timer), rifiuta la risposta.

**prepareEvaluation(): void**
- Questo metodo chiude il turno ('EVALUATING').
- Scorre tutti i giocatori vivi: se qualcuno non ha dato risposta (stringa vuota o assente), imposta d'ufficio la risposta [NESSUNA RISPOSTA ENTRO IL TEMPO].

**pplyResults(array , ...): void**
- Riceve in input i risultati generati dall'Intelligenza Artificiale per questo turno.
- Mappa i risultati sui vari giocatori. Se un giocatore aveva [NESSUNA RISPOSTA...], imposta forzatamente l'outcome a 'LOSE_LIFE' penalizzandolo.
- Sottrae le vite a chi ha l'esito 'LOSE_LIFE'.
- Prepara i dati completi del turno nell'array $this->pendingRound, che poi verrà usato dal Foundation per persistere i risultati sul database.
- Verifica quanti giocatori sono rimasti in vita:
  - Se è l'ultimo sopravvissuto (o tutti sono morti in single player), cambia stato a 'FINISHED' e decreta il vincitore (o nessuno se pareggio fatale).
  - Altrimenti, passa alla fase 'RESULTS'.

**
extRound(string ): void**
- Passa la partita dal mostrare i risultati ('RESULTS') al turno successivo ('OPEN').
- Incrementa il contatore del round, salva il nuovo scenario e resetta il timer.

---

## 6. Cartella pp/Control

### Classe CAuth (File: pp/Control/CAuth.php)
Gestisce l'autenticazione: Login, Registrazione e Logout.

#### Metodi:

**showLogin() e showRegister()**
- Controllano se l'utente è già loggato (FSession::user() !== null). In tal caso, lo rimandano alla home.
- Altrimenti chiamano la vista ($this->view->render) per mostrare i rispettivi template.

**egister()**
- Recupera dati in POST ($_POST['username'], email, password) pulendo le stringhe (	rim, mb_strtolower).
- Chiama il metodo di utilità alidate() per validare i formati.
- Istanzia FPersistentManager (che vedremo dopo). Controlla se username o email esistono già sul database.
- Se tutto è ok, crea l'utente sul database hashandone la password con password_hash(..., PASSWORD_DEFAULT) e lo fa loggare usando FSession::login().
- Reindirizza alla home mostrando un messaggio di benvenuto. In caso di errore (es. DB offline) aggiunge un errore da mostrare nel form.

**login()**
- Simile a register, cerca l'utente per email nel database.
- Usa password_verify(, ->passwordHash) per confrontare la password in chiaro inviata col form con quella criptata nel DB. Se corrisponde, effettua il login.

**logout()**
- Invoca FSession::logout() e reindirizza alla home.

### Classe CAdmin (File: pp/Control/CAdmin.php)
Gestisce il pannello riservato all'amministratore (moderatore).

#### Metodi:

**equireAdmin()**
- Controlla la sessione. Se l'utente non è loggato o il campo is_admin è falso/vuoto, lo caccia reindirizzandolo alla home.

**dashboard()**
- Recupera tutte le partite attive e tutti gli utenti tramite FPersistentManager.
- Passa questi dati al template dmin_dashboard.tpl.

**	erminateGame(string ) e deleteUser(string )**
- Operazioni distruttive accessibili solo all'admin.
- 	erminateGame usa il manager per forzare lo stato di una partita a 'FINISHED', chiudendola in anticipo se per qualche motivo era bloccata o offensiva.
- deleteUser cancella un utente dal sistema (non può cancellare se stesso).

### Classe CGame (File: pp/Control/CGame.php)
È il controller più complesso del progetto. Coordina l'interazione tra utente, regole del gioco (EGame) e servizi esterni (IA per generare le storie).

#### Metodi principali:

**home()**
- Se loggato, mostra la dashboard con le partite recenti (cercate sul DB). Altrimenti, mostra la home pubblica.

**create() e join()**
- create legge configurazioni (vite, durata), genera un codice alfanumerico randomico di 6 lettere e crea la partita sul DB.
- join permette di entrare in partita usando il codice a 6 lettere, richiamando ddPlayer su EGame.

**show(string )**
- Renderizza l'interfaccia di gioco. Passa alla vista i dati della partita correnti.

**start(string )**
- L'utente host clicca "Inizia".
- Chiama l'IA (FAIService->createScenario) per generare l'inizio della storia, basandosi sui vecchi scenari (se ce ne sono) e il numero del round.
- Salva nel DB il nuovo stato della partita, aprendo ufficialmente il turno 1.

**nswer(string )**
- Riceve la mossa dell'utente (la frase che inserisce per sopravvivere).
- Valida la lunghezza.
- Usa il mutateGame (che blocca la riga del database per evitare concorrenze tra utenti) per salvare l'azione usando $game->submitAnswer.

**evaluate(string )**
- Scaduto il tempo del round, valuta i risultati tramite il metodo privato unEvaluation.
- Questo metodo usa FAIService->evaluateSurvival per far valutare all'IA se ogni singola mossa salva l'utente o lo fa morire.
- Usa FAIService->generateStory per unire l'esito delle mosse in un racconto testuale unificato.
- Applica i risultati alla partita (pplyResults).

**state(string )**
- Un endpoint API che restituisce JSON. Chiamato ogni secondo via AJAX dal frontend (JavaScript) per aggiornare la barra del tempo, vedere chi ha risposto e capire se il turno ha cambiato fase.
---

## 7. Cartella pp/Foundation (Parte 2)

### Classe FAIService (File: pp/Foundation/FAIService.php)
È il cuore dell'interazione con l'Intelligenza Artificiale (OpenAI o Ollama locale) che rende il gioco imprevedibile.

#### Metodi principali:

**__construct()**
- Legge le configurazioni (modello, URL, API key) dal file config.php.

**createScenario(array , int ): string**
- Richiede all'IA di generare una situazione di pericolo mortale in una sola frase (es. "Un orso ti ha bloccato nella tenda.").
- Passa all'IA gli scenari vecchi ($previousScenarios) per evitare che crei pericoli troppo simili.
- Fa fino a 3 tentativi. Se l'IA fallisce (es. restituisce testo non valido), cade nella funzione allbackScenario che usa un array di pericoli pre-impostati.

**evaluateSurvival(EGame ): array**
- Interroga l'IA in modo molto rigido ("Sei un giudice severo...") passandole lo scenario e tutte le risposte dei giocatori.
- L'IA deve rispondere **esclusivamente** con JSON e assegnare SAFE o LOSE_LIFE a ciascun ID giocatore.
- Imposta la temperatura (JUDGMENT_TEMPERATURE = 0.05) molto bassa per evitare che l'IA sia creativa e fare in modo che giudichi le regole in modo deterministico e logico.
- Anche qui, se l'IA fallisce o il server non risponde, c'è un allbackEvaluation che tira un "dado" (hash) per decidere se l'utente sopravvive in modo casuale ma ripetibile.

**generateStory(EGame , array ): array**
- Dopo aver deciso chi vive e chi muore, questa funzione viene chiamata per "colorare" il risultato, chiedendo all'IA di scrivere una breve storia (5 frasi) che giustifichi l'esito.
- Costruisce storie separate per ogni giocatore, forzando la frase finale richiesta ("Mario sopravvive e conserva la propria vita.").
- Verifica attentamente (storyContradictsOutcome) che il testo generato dall'IA non dica "sei morto" se in realtà il giocatore aveva vinto (SAFE).
- Usa una temperatura (STORY_TEMPERATURE = 0.45) intermedia per dare un po' di creatività senza sfociare nell'incoerenza.

**equestContent(...) e postJson(...)**
- Gestiscono fisicamente la richiesta HTTP con cURL (in PHP).
- Formattano il payload JSON a seconda che il provider configurato sia openai oppure ollama.

### Classe FPersistentManager (File: pp/Foundation/FPersistentManager.php)
Questa classe si occupa del salvataggio e recupero fisico dei dati su MySQL. Incapsula tutte le query SQL.

#### Metodi principali:

**createUser(string , string , string ): EUser**
- Esegue una query INSERT INTO users sfruttando i Prepared Statement (:username, ecc.) di PDO. Questo previene nativamente le SQL injection.

**createGame(EGame ): EGame**
- Avvia una transazione database ($this->database->beginTransaction()).
- Inserisce la partita in games.
- Recupera l'ID appena inserito con $this->database->lastInsertId().
- Cicla sull'array $game->players e li inserisce nella tabella game_players.
- Se tutto va a buon fine, esegue il commit(). Se fallisce a metà, catch intercetta e lancia ollBack() annullando tutto per mantenere coerente il DB.

**indGameByCode(string ): ?EGame**
- Metodo usato spessissimo. Chiama il metodo privato loadGame.

**mutateGame(string , callable ): EGame**
- Implementa un meccanismo molto sofisticato di lock ottimistico/pessimistico.
- Apre una transazione.
- Chiama loadGame(..., lock: true). Questa query aggiunge verosimilmente un FOR UPDATE (bloccando le righe su MySQL) in modo che se due utenti cliccano "Invia" nello stesso millisecondo, uno dei due processi PHP dovrà aspettare in fila.
- Esegue la $mutation (una funzione anonima passata dal controller, che modifica lo stato di EGame).
- Chiama saveGame() per scrivere i cambiamenti.
- Esegue commit().

**loadGame(...) e saveGame(...)**
- Metodi privati mastodontici.
- loadGame estrae la partita, fa una JOIN su game_players e users per prendere i nomi utente e, se la partita è in fase dei risultati, fa query su ounds e ound_results per caricare anche gli esiti e le storie lette dagli utenti.
- saveGame prende un oggetto EGame in memoria, controlla cos'è cambiato, esegue l'UPDATE su games, e INSERT / UPDATE su game_players.
- In più, se trova che $game->pendingRound non è nullo (cioè l'IA ha appena generato nuovi esiti nel controller), fa le INSERT nella tabella ounds e ound_results.

**Metodi minori**
- deleteUser, 	erminateGame, indAllActiveGames, ecc., usati prevalentemente dal controller Admin o da funzionalità accessorie, tutte tramite PDO::prepare.
---

## 8. Cartella public/js (Frontend JavaScript)

Questa cartella contiene i file JavaScript lato client (eseguiti dal browser). Servono a migliorare l'esperienza utente rendendola interattiva.

### pp.js (File: public/js/app.js)
File globale caricato in tutte le pagine.

#### Dettaglio del codice:
- document.documentElement.className += ' js-ready';
  Aggiunge la classe js-ready al tag <html>. Spesso usato nei file CSS per cambiare gli stili (es. nascondere elementi di fallback) sapendo che JavaScript funziona e può gestire logiche complesse.
- **Formattazione Codice Partita**:
  Recupera l'input con ID game-code. Se esiste, ascolta l'evento input (ogni volta che si digita qualcosa). Usa un'espressione regolare per rimuovere tutti i caratteri non alfanumerici, converte in maiuscolo e limita a 6 caratteri usando .slice(0, 6). Questo aiuta l'utente a non sbagliare a digitare il codice di partecipazione.
- **Gestione del Modale di Eliminazione**:
  Cerca se c'è un elemento [data-delete-dialog]. In tal caso:
  - Recupera tutti i pulsanti con l'attributo [data-delete-game].
  - Aggiunge un EventListener al clic di ciascun bottone.
  - Quando si clicca "Elimina", invece di inviare subito una richiesta, la pagina intercetta il clic. Legge il codice della partita da eliminare, aggiorna l'URL del form (<form action="...">) dentro il Dialog, imposta l'etichetta di testo corretta (es. "Sei sicuro di eliminare HDF3S?") e chiama .showModal() per mostrare la finestra di conferma.
  - C'è anche un gestore per il pulsante "Annulla" ([data-delete-cancel]) che fa .close() per nascondere il Dialog senza conseguenze.

### game.js (File: public/js/game.js)
Lo script vitale per il caricamento dinamico della partita. Si assicura che il timer scorra in tempo reale e che tutti i partecipanti vedano gli aggiornamenti in sync.

#### Dettaglio del codice:
- Si attiva solo se c'è l'elemento principale con data-game-code.
- **Stato iniziale (initial)**: Salva fase, round e numero giocatori leggendoli dagli attributi data-* impostati da PHP nel rendering della pagina.
- **serverNow()**: Restituisce il timestamp in secondi dal client. Serve per i calcoli sul tempo rimanente.
- **updateTimer()**:
  - Calcola la differenza in secondi tra la scadenza (deadline) e l'ora attuale (serverNow()).
  - Aggiorna il testo dell'orologio (es.  0:15). Se mancano meno di 10 secondi, aggiunge la classe CSS 	imer-danger per colorare il timer di rosso o farlo lampeggiare.
  - Se il conto alla rovescia arriva a   e la pagina è l'host, chiama closeRound().
- **closeRound()**:
  - Viene eseguita alla fine del tempo.
  - Blocca l'invio manuale disabilitando il bottone e l'input di testo (eadOnly = true).
  - Prende il testo scritto fino a quel momento (bozza), crea un oggetto FormData e lo invia al server in background usando etch(..., {method: 'POST'}) aggiungendo l'impostazione fittizia utomatic=1. In questo modo, l'ultima idea digitata dall'utente, anche se non confermata manualmente, viene salvata.
  - Aspetta qualche millisecondo (grace period per coprire lag di rete) e poi l'Host forza il submit del form di valutazione (utoEvaluate.requestSubmit()), che ordina al server di far valutare all'IA il round.
- **Timer a ciclo continuo**: window.setInterval(updateTimer, 250) esegue il calcolo del tempo e l'aggiornamento grafico 4 volte al secondo (250 ms), garantendo che la decrescita del timer sembri fluida.
- **Polling (Richieste Ricorrenti)**:
  - Un altro setInterval configurato ogni 2,5 secondi (2500 ms).
  - Questo ciclo continua a fare una piccola chiamata etch invisibile all'indirizzo /api/game/CODICE.
  - PHP gli restituisce un JSON snello con solo i dati di fase (OPEN, RESULTS, ecc.), giocatori e round.
  - JavaScript confronta questo JSON con la variabile initial. Se il round è cambiato (es. l'host ha avviato il turno successivo), o la fase è passata a valutazione, o si è unito un nuovo giocatore... lo script ricarica l'intera pagina istantaneamente (window.location.reload()). Questo permette ai giocatori connessi (che non sono host) di vedere il cambio di schermata nello stesso istante dell'host, senza dover premere F5 manualmente!

---

**Fine della documentazione.**
Questa analisi dettagliata riga per riga di tutto il codice sorgente (Controller, Modelli, Entità, Views, e Frontend) fornisce una panoramica completa, a scopo didattico, della struttura del progetto e delle sue logiche architetturali.

---

## 9. Aggiornamento: Normalizzazione del Database (Nuove Modifiche)

Il codice e il database hanno subìto un'importante modifica architetturale. Inizialmente, l'intero stato della partita era probabilmente salvato in modo denormalizzato o compresso (es. json). Ora il DB è stato perfettamente normalizzato in tabelle relazionali distinte, migliorando la pulizia e l'eleganza del progetto senza risultare eccessivamente "over-engineered". Le pratiche utilizzate rispettano pienamente le convenzioni didattiche (Model-View-Controller e Pattern Entity).

### Nuova Struttura Relazionale
1. **games**: Contiene solo i metadati generali della partita (codice, host, numero di vite iniziali, giocatore vincitore). Non contiene più lo stato del turno attivo.
2. **game_players**: Gestisce la relazione molti-a-molti tra games e users. Tiene traccia di quanti giocatori partecipano e delle loro vite rimanenti (una colonna dedicata e facilmente aggiornabile).
3. **rounds**: Ogni volta che l'host avvia un nuovo round (Fase "OPEN"), viene creata una nuova riga in questa tabella. Memorizza lo scenario generato dall'IA, la scadenza (deadline_at) e lo stato specifico di quel turno (OPEN, EVALUATING, COMPLETED).
4. **round_results**: La tabella più granulare. Per ogni round e per ogni giocatore, viene inserita una riga. Quando l'utente invia la propria risposta testuale per sopravvivere, questa finisce nel campo nswer. Dopo la valutazione dell'IA, la stessa riga viene aggiornata con l'esito (outcome = SAFE/LOSE_LIFE), il racconto personalizzato (story) e le vite calcolate post-danno (lives_after).

### Modifiche alle Entità e ai Manager (Riga per Riga)
- **EGame.php**: I campi della classe sono stati estesi e frammentati. Oltre alle vecchie proprietà, EGame ora mappa i dati combinati provenienti dalle JOIN del DB: $currentRoundId, $roundStatus, $scenario. Questo evita di dover fare query multiple quando il Controller ha bisogno dell'intero stato per il frontend.
- **FPersistentManager.php / loadGame**: I metodi di caricamento (load) adesso utilizzano istruzioni JOIN su MySQL per "assemblare" la partita partendo dalle righe frammentate in games, ounds, game_players e ound_results. 
- **CGame.php**: La logica del controller è rimasta fluida. Quando un utente fa l'azione (es. submitAnswer), il controller chiama il manager che esegue un semplice UPDATE round_results SET answer = .... Non ci sono più enormi JSON da parsare e ricodificare.

### Conformità e Stile
Il codice modificato è **ben scritto** e aderisce rigorosamente alle "slide" del corso:
- Non abusa di costrutti PHP avanzati fuori programma (niente Traits complessi o Reflection).
- Il routing e la separazione dei livelli restano immutati (Control parla con Foundation per il DB e passa i dati a Presentation).
- La scelta di normalizzare il database in tabelle separate per i round e i risultati è il corretto approccio accademico per gestire la molteplicità (1 partita -> N round -> N risultati_giocatore).

