<dialog class="app-modal" id="app-confirm-dialog" data-app-modal aria-labelledby="app-confirm-dialog-title" aria-describedby="app-confirm-dialog-desc" aria-busy="false">
    <form class="app-modal-form" method="POST" action="#" data-app-modal-form>
        @csrf
        <input type="hidden" name="_method" value="DELETE" data-confirm-method>
        <div class="app-modal-box">
            <header class="app-modal-head">
                <span class="app-modal-icon app-modal-icon--danger" data-confirm-icon-holder aria-hidden="true">
                    <svg data-confirm-icon-target><use href="#icon-trash"></use></svg>
                </span>
                <div class="app-modal-title">
                    <h2 id="app-confirm-dialog-title" data-confirm-title-target>Confirm action</h2>
                    <p id="app-confirm-dialog-desc" data-confirm-desc-target>Please confirm this action before continuing.</p>
                </div>
                <button class="app-modal-close" type="button" data-app-modal-close aria-label="Close dialog"><svg aria-hidden="true"><use href="#icon-x"></use></svg></button>
            </header>
            <div class="app-modal-body">
                <p class="app-modal-intro" data-confirm-message-target>Are you sure you want to continue?</p>
                <p class="app-modal-error" data-app-modal-error hidden><strong data-app-modal-error-title>Unable to complete action</strong><span data-app-modal-error-message></span></p>
            </div>
            <footer class="app-modal-foot">
                <button class="button button-muted" type="button" data-app-modal-close>Cancel</button>
                <button class="button button-danger app-modal-confirm" type="submit" data-app-modal-confirm data-busy-label="Deleting..." data-app-modal-autofocus data-confirm-label-target>Confirm</button>
            </footer>
        </div>
    </form>
</dialog>