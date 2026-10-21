/*
 * Admin-assisted athlete password reset.
 *
 * Wires up the "Reset Password" action in Athlete Management:
 *   1. Opens a confirmation modal explaining a temporary password will be
 *      generated (no email is involved).
 *   2. Submits the reset request to the server via fetch (JSON), showing a
 *      loading state and preventing duplicate submissions while it runs.
 *   3. On success, opens a one-time modal showing the generated temporary
 *      password with a Copy Password button. The password is never stored
 *      in the page, session, or logs - it lives only in this response.
 *   4. On failure, shows the server's error inside the confirmation modal.
 */
(function () {
    'use strict';

    var CONFIRM_DIALOG_ID = 'password-reset-confirm-modal';
    var SUCCESS_DIALOG_ID = 'password-reset-success-modal';

    var confirmDialog = document.getElementById(CONFIRM_DIALOG_ID);
    var successDialog = document.getElementById(SUCCESS_DIALOG_ID);
    var confirmForm = confirmDialog && confirmDialog.querySelector('form');
    var secretInput = document.getElementById('password-reset-secret-value');
    var copyButton = document.querySelector('[data-copy-password]');
    var warningText = document.getElementById('password-reset-warning');

    var currentUrl = '';
    var busy = false;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setBusy(isBusy) {
        busy = isBusy;
        var confirmButton = confirmDialog ? confirmDialog.querySelector('[data-app-modal-confirm]') : null;
        if (confirmButton) {
            if (isBusy) {
                confirmButton.dataset.loadingLabel = confirmButton.textContent;
                confirmButton.textContent = confirmButton.getAttribute('data-busy-label') || 'Resetting...';
                confirmButton.classList.add('is-loading');
                confirmButton.disabled = true;
            } else {
                if (confirmButton.dataset.loadingLabel) {
                    confirmButton.textContent = confirmButton.dataset.loadingLabel;
                }
                confirmButton.classList.remove('is-loading');
                confirmButton.disabled = false;
            }
        }
    }

    function showConfirmError(message) {
        var box = confirmDialog ? confirmDialog.querySelector('[data-app-modal-error]') : null;
        var text = box && box.querySelector('[data-app-modal-error-message]');
        if (box && text) {
            text.textContent = message;
            box.hidden = false;
            box.setAttribute('role', 'alert');
        }
    }

    function hideConfirmError() {
        var box = confirmDialog ? confirmDialog.querySelector('[data-app-modal-error]') : null;
        var text = box && box.querySelector('[data-app-modal-error-message]');
        if (box) {
            box.hidden = true;
            box.removeAttribute('role');
        }
        if (text) { text.textContent = ''; }
    }

    function openDialog(dialog) {
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        hideConfirmError();
        setBusy(false);
        /* Opening the card must never move the page. */
        dialog._scrollY = window.scrollY || window.pageYOffset || 0;
        dialog.showModal();
        window.requestAnimationFrame(function () {
            var target = dialog.querySelector('[data-app-modal-autofocus]')
                || dialog.querySelector('input, select, textarea, button:not([data-app-modal-close])');
            if (target) {
                try {
                    target.focus({ preventScroll: true });
                } catch (e) {
                    target.focus();
                }
            }
            window.scrollTo(0, dialog._scrollY);
        });
    }

    function closeDialog(dialog) {
        if (!dialog || typeof dialog.close !== 'function') {
            return;
        }
        var scrollY = dialog._scrollY || window.scrollY || window.pageYOffset || 0;
        dialog.close();
        window.scrollTo(0, scrollY);
    }

    // Open the confirmation modal with the reset action's details.
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-password-reset]');
        if (!opener) {
            return;
        }
        event.preventDefault();
        if (busy) {
            return;
        }

        currentUrl = opener.getAttribute('data-confirm-url') || '';

        var title = opener.getAttribute('data-confirm-title') || 'Reset Athlete Password';
        var message = opener.getAttribute('data-confirm-message') || '';
        var label = opener.getAttribute('data-confirm-label') || 'Reset Password';
        var athleteName = opener.getAttribute('data-athlete-name') || '';

        var dialogTitle = confirmDialog ? confirmDialog.querySelector('#password-reset-confirm-title') : null;
        var dialogDesc = confirmDialog ? confirmDialog.querySelector('#password-reset-confirm-desc') : null;
        var dialogIntro = confirmDialog ? confirmDialog.querySelector('.app-modal-intro') : null;
        var confirmButton = confirmDialog ? confirmDialog.querySelector('[data-app-modal-confirm]') : null;

        if (dialogTitle) { dialogTitle.textContent = title; }
        if (dialogDesc) { dialogDesc.textContent = message; }
        if (dialogIntro) { dialogIntro.textContent = message; }
        if (confirmButton) {
            confirmButton.textContent = label;
            confirmButton.setAttribute('data-busy-label', opener.getAttribute('data-confirm-busy-label') || 'Resetting...');
        }

        openDialog(confirmDialog);
    });

    // Handle the confirmation submission via fetch (JSON).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form !== confirmForm) {
            return;
        }
        event.preventDefault();

        if (busy || !currentUrl) {
            return;
        }

        var tokenInput = confirmForm.querySelector('input[name="_token"]');
        var token = tokenInput ? tokenInput.value : '';

        var body = new FormData();
        body.append('_token', token);

        setBusy(true);

        fetch(currentUrl, {
            method: 'POST',
            body: body,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            setBusy(false);

            if (result.ok && result.data && result.data.temporary_password) {
                closeDialog(confirmDialog);
                currentUrl = '';

                if (secretInput) {
                    secretInput.value = result.data.temporary_password;
                }
                var nameEl = document.getElementById('password-reset-athlete-name');
                if (nameEl && result.data.athlete_name) {
                    nameEl.textContent = result.data.athlete_name;
                }
                if (warningText) { warningText.hidden = true; }

                openDialog(successDialog);
                return;
            }

            var message = 'The server could not complete the password reset.';
            if (result.data && result.data.message) {
                message = result.data.message;
            } else if (result.data && result.data.errors) {
                var first = result.data.errors[Object.keys(result.data.errors)[0]];
                message = Array.isArray(first) ? first[0] : String(first);
            }
            showConfirmError(message);
        }).catch(function () {
            setBusy(false);
            showConfirmError('Unable to reach the server. Please check your connection and try again.');
        });
    });

    // Copy the temporary password to the clipboard.
    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-copy-password]')) {
            return;
        }
        event.preventDefault();

        var value = secretInput ? secretInput.value : '';
        if (!value) {
            return;
        }

        var promise = navigator.clipboard && navigator.clipboard.writeText
            ? navigator.clipboard.writeText(value)
            : Promise.resolve();

        promise.then(function () {
            if (warningText) {
                warningText.hidden = false;
                warningText.textContent = 'Copied!';
                window.setTimeout(function () { warningText.hidden = true; }, 2000);
            }
        }).catch(function () {
            // Fallback for browsers without Clipboard API.
            try {
                secretInput.select();
                secretInput.setSelectionRange(0, 99999);
                document.execCommand('copy');
                if (warningText) {
                    warningText.hidden = false;
                    warningText.textContent = 'Copied!';
                    window.setTimeout(function () { warningText.hidden = true; }, 2000);
                }
            } catch (e) {
                /* Clipboard unavailable; the password remains visible to copy manually. */
            }
        });
    });

    // Clear the visible password when the success modal is closed so it is
    // shown only once and never lingers in the interface.
    document.addEventListener('click', function (event) {
        var closer = event.target.closest('[data-app-modal-close]');
        if (!closer) {
            return;
        }
        var dialog = closer.closest('dialog#' + SUCCESS_DIALOG_ID);
        if (!dialog) {
            return;
        }
        if (secretInput) {
            secretInput.value = '';
        }
        currentUrl = '';
    });
})();
