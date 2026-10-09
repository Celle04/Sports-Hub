/*
 * Application review dialogs: in-system confirmation modals for admin review
 * actions (approve / reject / under review / waitlist / request documents /
 * generic status change) plus a small toast notification system.
 *
 * Every modal is a native <dialog> and every submission goes through fetch so
 * validation errors can be shown inside the modal while the server remains the
 * only authority on approvals and document verification.
 */
(function () {
    'use strict';

    var BUSY_ATTR = 'aria-busy';

    function closest(node, selector) {
        return node && node.closest ? node.closest(selector) : null;
    }

    /* ---- Toast notifications ---- */
    var toastRegion = null;

    function ensureToastRegion() {
        if (!toastRegion) {
            toastRegion = document.createElement('div');
            toastRegion.className = 'app-toast-region';
            toastRegion.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastRegion);
        }
        return toastRegion;
    }

    function dismissToast(toast) {
        if (toast.classList.contains('is-leaving')) {
            return;
        }
        toast.classList.add('is-leaving');
        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 190);
    }

    function showToast(title, message, tone) {
        var container = ensureToastRegion();
        var toast = document.createElement('div');
        toast.className = 'app-toast' + (tone === 'error' ? ' app-toast--error' : tone === 'warning' ? ' app-toast--warning' : '');

        var icon = document.createElement('span');
        icon.className = 'app-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        var use = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        use.setAttribute('viewBox', '0 0 24 24');
        var glyph = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        glyph.setAttribute('href', tone === 'warning' ? '#icon-warning' : '#icon-check');
        use.appendChild(glyph);
        icon.appendChild(use);
        toast.appendChild(icon);

        var copy = document.createElement('div');
        copy.className = 'app-toast-copy';
        var strong = document.createElement('strong');
        strong.textContent = title;
        var text = document.createElement('p');
        text.textContent = message;
        copy.appendChild(strong);
        copy.appendChild(text);
        toast.appendChild(copy);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'app-toast-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        var cx = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        cx.setAttribute('viewBox', '0 0 24 24');
        var cu = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        cu.setAttribute('href', '#icon-x');
        cx.appendChild(cu);
        close.appendChild(cx);
        close.addEventListener('click', function () { dismissToast(toast); });
        toast.appendChild(close);

        container.appendChild(toast);
        window.setTimeout(function () { dismissToast(toast); }, 5000);
    }

    function noticeTitleFor(message) {
        var text = String(message || '').toLowerCase();
        if (text.indexOf('verified') !== -1) return 'Document Verified';
        if (text.indexOf('document') !== -1) return 'Documents Updated';
        if (text.indexOf('approve') !== -1) return 'Application Approved';
        if (text.indexOf('reject') !== -1) return 'Application Rejected';
        return 'Application Updated';
    }

    /* ---- Modal open / close ---- */
    function openDialog(dialog, opener) {
        if (dialog.open) {
            return;
        }
        dialog._opener = opener || null;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        // Move focus to the first meaningful control after the dialog paints.
        window.requestAnimationFrame(function () {
            var target = dialog.querySelector('[data-app-modal-autofocus]')
                || dialog.querySelector('input, select, textarea, button:not([data-app-modal-close])');
            if (target) { target.focus(); }
        });
    }

    function closeDialog(dialog) {
        if (!dialog.open) {
            return;
        }
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        if (dialog._opener && typeof dialog._opener.focus === 'function') {
            dialog._opener.focus();
        }
    }

    function isBusy(dialog) {
        return dialog.getAttribute(BUSY_ATTR) === 'true';
    }

    document.addEventListener('click', function (event) {
        var opener = closest(event.target, '[data-app-modal-open]');
        if (!opener) { return; }
        event.preventDefault();
        var dialog = document.getElementById(opener.getAttribute('data-app-modal-open'));
        if (dialog) { openDialog(dialog, opener); }
    });

    document.addEventListener('click', function (event) {
        var closer = closest(event.target, '[data-app-modal-close]');
        if (!closer) { return; }
        var dialog = closest(event.target, 'dialog.app-modal');
        if (dialog && !isBusy(dialog)) { closeDialog(dialog); }
    });

    // Clicking the backdrop (the transparent dialog area outside the box)
    // closes the modal, but never while a submission is in progress.
    document.addEventListener('mousedown', function (event) {
        if (event.target !== null && event.target.nodeType === 1
            && event.target.tagName === 'DIALOG'
            && event.target.classList.contains('app-modal')
            && !isBusy(event.target)) {
            closeDialog(event.target);
        }
    });

    // ESC: native dialog closes on Escape unless the submit is running.
    document.addEventListener('cancel', function (event) {
        if (event.target instanceof HTMLDialogElement && isBusy(event.target)) {
            event.preventDefault();
        }
    }, true);

    /* ---- Reject reason "Other" reveal ---- */
    document.addEventListener('change', function (event) {
        var reason = closest(event.target, '[data-app-reject-reason]');
        if (!reason) { return; }
        var wrapper = closest(reason, '[data-app-reject-other-wrap]');
        if (wrapper) {
            wrapper.hidden = reason.value !== 'Other';
            if (!wrapper.hidden) {
                var other = wrapper.querySelector('[data-app-reject-other], textarea');
                if (other) { other.focus(); }
            }
        }
    });

    /* ---- Generic status form gate ---- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-app-status-form]')) {
            return;
        }
        event.preventDefault();
        var dialog = document.getElementById('app-modal-status');
        if (!dialog) { return; }

        var select = form.querySelector('select[name="status"]');
        var notes = form.querySelector('textarea[name="review_notes"]');
        var value = select ? select.value : '';
        var targetValue = dialog.querySelector('[data-app-status-value]');
        var targetInput = dialog.querySelector('[data-app-status-target]');
        var targetNotes = dialog.querySelector('textarea[name="review_notes"]');

        if (targetValue) { targetValue.textContent = value || '—'; }
        if (targetInput) { targetInput.value = value || ''; }
        if (targetNotes && notes) { targetNotes.value = notes.value; }

        openDialog(dialog, form.querySelector('button[type="submit"]'));
    });

    /* ---- Modal form submission via fetch ---- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) { return; }
        var dialog = closest(form, 'dialog.app-modal[data-app-modal]');
        if (!dialog || !dialog.open) { return; }
        event.preventDefault();

        // Resolve a custom "Other" rejection reason before reading the form.
        var reason = form.querySelector('[data-app-reject-reason]');
        if (reason && reason.value === 'Other') {
            var custom = form.querySelector('[data-app-reject-other]');
            if (!custom || !custom.value.trim()) {
                showFormError(dialog, 'Please describe the rejection reason.');
                if (custom) { custom.focus(); }
                return;
            }
            reason.value = custom.value.trim();
        }

        setBusy(dialog, true);
        hideFormError(dialog);

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            if (response.ok) {
                // The server redirects back to the review page with a flash
                // success message; navigate there so the status refreshes.
                window.location.href = response.redirected ? response.url : window.location.href;
                return null;
            }
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                var message = 'The server could not complete this action.';
                if (data && data.message) {
                    message = data.message;
                } else if (data && data.errors) {
                    var firstError = data.errors[Object.keys(data.errors)[0]];
                    message = Array.isArray(firstError) ? firstError[0] : String(firstError);
                }
                showFormError(dialog, message ? message.replace(/^\s*|\s*$/g, '') : 'The server could not complete this action.');
                setBusy(dialog, false);
            });
        }).catch(function () {
            showFormError(dialog, 'Unable to reach the server. Please check your connection and try again.');
            setBusy(dialog, false);
        });
    });

    function setBusy(dialog, busy) {
        dialog.setAttribute(BUSY_ATTR, busy ? 'true' : 'false');

        var confirmButton = dialog.querySelector('[data-app-modal-confirm]');
        if (confirmButton) {
            if (busy) {
                confirmButton.dataset.loadingLabel = confirmButton.textContent;
                confirmButton.dataset.wasDisabled = String(confirmButton.disabled);
                confirmButton.textContent = confirmButton.getAttribute('data-busy-label') || 'Processing...';
                confirmButton.classList.add('is-loading');
                confirmButton.disabled = true;
            } else {
                if (confirmButton.dataset.loadingLabel) {
                    confirmButton.textContent = confirmButton.dataset.loadingLabel;
                }
                confirmButton.classList.remove('is-loading');
                confirmButton.disabled = confirmButton.dataset.wasDisabled === 'true';
                delete confirmButton.dataset.wasDisabled;
            }
        }

        dialog.querySelectorAll('button:not([data-app-modal-confirm])').forEach(function (button) {
            button.disabled = busy;
        });

        if (busy) {
            hideFormError(dialog);
        }
    }

    function showFormError(dialog, message) {
        var box = dialog.querySelector('[data-app-modal-error]');
        if (!box) { return; }
        var text = box.querySelector('[data-app-modal-error-message]');
        if (text) { text.textContent = message; }
        box.hidden = false;
        box.setAttribute('role', 'alert');
    }

    function hideFormError(dialog) {
        var box = dialog.querySelector('[data-app-modal-error]');
        if (!box) { return; }
        box.hidden = true;
        box.removeAttribute('role');
        var text = box.querySelector('[data-app-modal-error-message]');
        if (text) { text.textContent = ''; }
    }

    /* ---- Turn server flash notices into toasts on load ---- */
    function init() {
        document.querySelectorAll('.notice[data-toast]').forEach(function (notice) {
            showToast(noticeTitleFor(notice.textContent), notice.textContent.replace(/^\s*|\s*$/g, ''), 'success');
            if (notice.parentNode) { notice.parentNode.removeChild(notice); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();