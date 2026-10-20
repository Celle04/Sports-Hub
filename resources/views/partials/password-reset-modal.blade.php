{{-- Admin-assisted athlete password reset modals. --}}
{{-- Confirm modal: opened before generating a temporary password. --}}
<dialog class="app-modal" id="password-reset-confirm-modal" data-app-modal aria-labelledby="password-reset-confirm-title" aria-describedby="password-reset-confirm-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="#" data-app-modal-form>
        @csrf
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--warning" aria-hidden="true">
                    <svg><use href="#icon-lock"></use></svg>
                </span>
                <div class="app-modal-title">
                    <h2 id="password-reset-confirm-title">Reset Athlete Password</h2>
                    <p id="password-reset-confirm-desc"></p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro"></p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to reset password</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-muted" type="button" data-app-modal-close>Cancel</button>
                <button class="button button-warning" type="submit" data-app-modal-confirm data-busy-label="Resetting..." data-app-modal-autofocus>Reset Password</button>
            </footer>
        </div>
    </form>
</dialog>

{{-- Success modal: shows the one-time temporary password. --}}
<dialog class="app-modal" id="password-reset-success-modal" data-app-modal aria-labelledby="password-reset-success-title" aria-describedby="password-reset-success-desc" aria-busy="false">
    <div class="app-modal-box">
        <header class="app-modal-head">
            <span class="app-modal-icon app-modal-icon--success" aria-hidden="true">
                <svg><use href="#icon-check"></use></svg>
            </span>
            <div class="app-modal-title">
                <h2 id="password-reset-success-title">Password Reset Successful</h2>
                <p id="password-reset-success-desc">A temporary password has been generated for <strong id="password-reset-athlete-name"></strong>.</p>
            </div>
            <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
        </header>
        <div class="app-modal-body">
            <p class="app-modal-intro">
                This is the only time the temporary password will be shown. Copy it now and share it with the athlete through a non-electronic channel.
            </p>
            <div class="password-reset-secret">
                <label>Temporary password</label>
                <div class="password-reset-secret-field">
                    <input type="text" id="password-reset-secret-value" readonly spellcheck="false">
                    <button class="button button-secondary" type="button" data-copy-password>Copy Password</button>
                </div>
                <p class="password-reset-warning" id="password-reset-warning">Copied!</p>
            </div>
        </div>
        <footer class="app-modal-foot">
            <button class="button button-muted" type="button" data-app-modal-close>Close</button>
        </footer>
    </div>
</dialog>
