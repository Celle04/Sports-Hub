// Filter forms apply themselves when a filter changes, so the admin does not
// have to press a button after every adjustment. Each form keeps an explicit
// submit button in the markup as the no JavaScript fallback.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach((control) => {
            control.addEventListener('change', () => form.submit());
        });
    });
});