/**
 * Aggiunge la classe js-ready al documento per indicare che JavaScript è abilitato.
 */
document.documentElement.className += ' js-ready';

/**
 * Gestisce l'input del codice partita, forzando il maiuscolo e limitando a 6 caratteri alfanumerici.
 */
var codeInput = document.getElementById('game-code');
if (codeInput) {
    codeInput.addEventListener('input', function() {
        codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
    });
}

/**
 * Inizializza e gestisce il dialog per l'eliminazione di una partita.
 */
var deleteDialog = document.querySelector('[data-delete-dialog]');
if (deleteDialog) {
    var deleteForm = deleteDialog.querySelector('[data-delete-form]');
    var deleteCode = deleteDialog.querySelector('[data-delete-code]');
    var deleteButtons = document.querySelectorAll('[data-delete-game]');
    
    // Associa l'evento click a ogni pulsante di eliminazione per aprire il dialog con i dati corretti
    for (var i = 0; i < deleteButtons.length; i++) {
        deleteButtons[i].addEventListener('click', function(e) {
            var button = e.currentTarget;
            var code = button.getAttribute('data-delete-game');
            var label = button.getAttribute('data-delete-label') || code;
            var baseUrl = document.body.getAttribute('data-base-url') || '';
            
            // Imposta il testo di conferma e l'URL di invio del form
            deleteCode.textContent = label;
            deleteForm.action = baseUrl + '/game/' + code + '/delete';
            deleteDialog.showModal();
        });
    }
    
    // Chiude il dialog al clic sul pulsante annulla
    deleteDialog.querySelector('[data-delete-cancel]').addEventListener('click', function() {
        deleteDialog.close();
    });
}
