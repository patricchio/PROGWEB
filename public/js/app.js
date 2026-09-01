document.documentElement.classList.add('js-ready');

const codeInput = document.querySelector('#game-code');
if (codeInput) {
    codeInput.addEventListener('input', () => {
        codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
    });
}

const deleteDialog = document.querySelector('[data-delete-dialog]');
if (deleteDialog) {
    const deleteForm = deleteDialog.querySelector('[data-delete-form]');
    const deleteCode = deleteDialog.querySelector('[data-delete-code]');
    document.querySelectorAll('[data-delete-game]').forEach((button) => {
        button.addEventListener('click', () => {
            const code = button.dataset.deleteGame;
            deleteCode.textContent = button.dataset.deleteLabel || code;
            deleteForm.action = `${document.body.dataset.baseUrl || ''}/game/${code}/delete`;
            deleteDialog.showModal();
        });
    });
    deleteDialog.querySelector('[data-delete-cancel]').addEventListener('click', () => deleteDialog.close());
}
