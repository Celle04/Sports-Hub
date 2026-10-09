/*
 * Reusable in-app confirmation dialog.
 *
 * Renders once per page (partials/confirm-dialog.blade.php). Any element with
 * [data-confirm-dialog] opens it as a <dialog> modal with a customisable title,
 * message, confirm label, icon and HTTP method. The confirm action is submitted
 * with fetch (handled by app-review.js) so the dialog shows a loading state and
 * in-dialog errors while the server stays the only authority, then navigates to
 * the server's redirect so the list refreshes and the flash notice confirms it.
 *
 * Attribute API (all optional except data-confirm-url):
 *   data-confirm-dialog      marks the trigger
 *   data-confirm-title       dialog heading            (default "Confirm action")
 *   data-confirm-message     body copy                 (default "Are you sure you want to continue?")
 *   data-confirm-label       confirm button text       (default "Confirm")
 *   data-confirm-busy-label  text while submitting     (default "Deleting...")
 *   data-confirm-icon        icon id, e.g. trash/check/warning/file (default "trash")
 *   data-confirm-variant     icon tone: danger/success/warning/info (default "danger")
 *   data-confirm-method      HTTP method               (default "DELETE")
 *   data-confirm-url         destination of the request (required)
 *
 * If the trigger sits inside a <form>, its named fields (minus _token/_method
 * and buttons) are carried along with the submission so forms that collect a
 * reason/remarks keep working.
 */
(function () {
    'use strict';

    var DIALOG_ID = 'app-confirm-dialog';

    function closest(node, selector) {
        return node && node.closest ? node.closest(selector) : null;
    }

    function setText(node, text, fallback) {
        if (node) {
            node.textContent = text || fallback;
        }
    }

    function cloneSourceFields(dialog, sourceForm) {
        dialog.querySelectorAll('input.js-confirm-clone').forEach(function (input) {
            input.parentNode.removeChild(input);
        });
        if (!sourceForm) {
            return;
        }
        sourceForm.querySelectorAll('input, select, textarea').forEach(function (field) {
            var name = field.getAttribute('name');
            var type = String(field.getAttribute('type') || '').toLowerCase();
            if (!name || name === '_token' || name === '_method') {
                return;
            }
            if (type === 'button' || type === 'submit' || type === 'reset') {
                return;
            }
            var clone = document.createElement('input');
            clone.type = 'hidden';
            clone.name = name;
            clone.value = field.value;
            clone.className = 'js-confirm-clone';
            dialog.querySelector('[data-confirm-method]').parentNode.appendChild(clone);
        });
    }

    function openDialog(dialog, opener) {
        var form = dialog.querySelector('form');
        var methodField = dialog.querySelector('[data-confirm-method]');

        setText(dialog.querySelector('[data-confirm-title-target]'), opener.getAttribute('data-confirm-title'), 'Confirm action');
        setText(dialog.querySelector('[data-confirm-desc-target]'), opener.getAttribute('data-confirm-message'), '');
        setText(dialog.querySelector('[data-confirm-message-target]'), opener.getAttribute('data-confirm-message'), 'Are you sure you want to continue?');

        var confirmButton = dialog.querySelector('[data-app-modal-confirm]');
        if (confirmButton) {
            setText(confirmButton, opener.getAttribute('data-confirm-label'), 'Confirm');
            confirmButton.setAttribute('data-busy-label', opener.getAttribute('data-confirm-busy-label') || 'Deleting...');
            confirmButton.classList.remove('is-loading');
            confirmButton.disabled = false;
        }

        var use = dialog.querySelector('[data-confirm-icon-target] use');
        if (use) {
            use.setAttribute('href', '#icon-' + (opener.getAttribute('data-confirm-icon') || 'trash'));
        }
        var iconHolder = dialog.querySelector('[data-confirm-icon-holder]');
        if (iconHolder) {
            iconHolder.className = 'app-modal-icon app-modal-icon--' + (opener.getAttribute('data-confirm-variant') || 'danger');
        }

        if (form) {
            form.action = opener.getAttribute('data-confirm-url') || form.action;
        }
        if (methodField) {
            methodField.value = opener.getAttribute('data-confirm-method') || 'DELETE';
        }

        var sourceForm = closest(opener, 'form');
        cloneSourceFields(dialog, sourceForm);

        var error = dialog.querySelector('[data-app-modal-error]');
        if (error) {
            error.hidden = true;
        }

        dialog._opener = opener;
        dialog.setAttribute('aria-busy', 'false');
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        window.requestAnimationFrame(function () {
            var target = dialog.querySelector('[data-app-modal-autofocus]')
                || dialog.querySelector('input, select, textarea, button:not([data-app-modal-close])');
            if (target) { target.focus(); }
        });
    }

    // Keep cloned source values in sync if the user edits the original inputs
    // after focusing a field but before confirming.
    document.addEventListener('click', function (event) {
        var dialog = closest(event.target, 'dialog#' + DIALOG_ID);
        if (!dialog || !closest(event.target, '[data-app-modal-confirm]')) {
            return;
        }
        var source = dialog._opener ? closest(dialog._opener, 'form') : null;
        cloneSourceFields(dialog, source);
    }, true);

    document.addEventListener('click', function (event) {
        var opener = closest(event.target, '[data-confirm-dialog]');
        if (!opener) {
            return;
        }
        event.preventDefault();
        var dialog = document.getElementById(DIALOG_ID);
        if (dialog) {
            openDialog(dialog, opener);
        }
    });
})();