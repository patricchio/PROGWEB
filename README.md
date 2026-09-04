App: Death by AI (Party/Survival Game Narrativo)

Descrizione: 

L'applicazione Death by AI è un videogioco web multiplayer basato su meccaniche di sopravvivenza ed esplorazione narrativa testuale. L'app permette agli utenti (da 1 a 5) di affrontarsi in partite a turni, dove una Intelligenza Artificiale agisce come game master e giudice. A ogni round, l'IA genera proceduralmente un nuovo scenario di pericolo mortale; i giocatori devono scrivere e inviare un'azione testuale descrivendo come intendono salvarsi. Il sistema calcola gli esiti automaticamente (decretando chi sopravvive e chi perde una vita) accompagnati da un racconto testuale narrativo dell'evento. Include inoltre meccaniche come configurazione del timer e delle vite, lobby con codici di accesso, aggiornamenti asincroni in tempo reale.

Tipologie di utenti: 

Amministratore 

Giocatore (Utente registrato) 

Ospite (Utente non registrato) 

Funzioni degli utenti: 

Ospite (Utente non registrato): 
- ha la possibilità di registrarsi creando il proprio account;
- ha la possibilità di effettuare il login o navigare nella homepage per comprendere il funzionamento generale.

Giocatore (Utente registrato): 
- tutte le funzionalità offerte all'ospite (utente non registrato);
- può creare una nuova partita scegliendo le impostazioni iniziali (numero massimo di vite, durata del timer del round);
- può partecipare a una partita in lobby inserendo il codice univoco di 6 lettere;
- può leggere l'esito degli eventi generati (scenari di pericolo e resoconti narrativi) interagendo con l'interfaccia a schermo;
- può decidere e scrivere la propria azione successiva in formato testuale libero per cercare di sopravvivere alla minaccia del turno, prima dello scadere del tempo;
- subisce aggiornamenti automatici delle proprie statistiche nel database (es. riduzione dei Punti Vita) calcolati dal giudice IA.

Amministratore: 
- accede a una dashboard dedicata in cui può osservare la lista di tutte le partite in corso;
- gestisce le partite (può terminare e chiudere anticipatamente i game fermi o inattivi);
- gestisce il database degli utenti registrati (visualizzazione, eliminazione degli account).


RESPONSABILITA’: 

PHP: backend interazione con database e logica completa del gioco 
- Gestione della creazione della partita e dei codici di invito (Lobby)
- Calcolare le vite rimanenti dei giocatori
- Gestire il timer e il flusso asincrono dei turni di gioco (Fasi OPEN, EVALUATING, RESULTS)
- Interazione tramite Web Service/API con un'IA esterna (OpenAI) o AI in locale (Ollama)
- Verificare i vincitori finali e smistare lo stato della partita
- Login, validazione form e gestione Sessioni.

Creazione degli oggetti (Pattern Entity): 
- User
- Game

JSON: per inviare e ricevere informazioni in modo formattato e strettamente tipizzato tra il server PHP e l'Intelligenza Artificiale, e per esporre le API usate dal frontend (polling di gioco).

JAVASCRIPT/CSS/HTML: frontend interfaccia grafica (pattern Presentation con motore Smarty), animazioni CSS per il timer, e utilizzo di JavaScript puro (Vanilla JS) per l'aggiornamento automatico della pagina e la manipolazione base del DOM.

MYSQL: database (tabelle relazionali, chiavi esterne e aggiornamento stati transazionali).
