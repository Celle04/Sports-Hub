document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = document.getElementById(toggle.dataset.passwordToggle);
            if (!input) {
                return;
            }

            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(showPassword));
            toggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            toggle.querySelector('use')?.setAttribute('href', showPassword ? '#icon-eye-off' : '#icon-eye');
        });
    });

    document.querySelectorAll('form[data-loading-label]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) {
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = form.dataset.loadingLabel;
        });
    });
});

/*
 * OTP entry: six single-digit boxes that auto-advance, accept paste and keep
 * a hidden input in sync. The code is assembled client-side and never appears
 * in the page markup.
 */
document.addEventListener('DOMContentLoaded', () => {
    const field = document.querySelector('[data-otp-field]');
    if (!field) {
        return;
    }

    const boxes = Array.from(field.querySelectorAll('.otp-box'));
    const hidden = field.querySelector('[data-otp-value]');
    if (!boxes.length || !hidden) {
        return;
    }

    const sync = () => {
        hidden.value = boxes.map((box) => box.value).join('');
    };

    const focusBox = (index) => {
        if (boxes[index]) {
            boxes[index].focus();
        }
    };

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            const digit = box.value.replace(/\D/g, '').slice(0, 1);
            box.value = digit;
            sync();
            if (digit !== '' && index < boxes.length - 1) {
                focusBox(index + 1);
            }
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && box.value === '' && index > 0) {
                event.preventDefault();
                focusBox(index - 1);
            }
            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusBox(index - 1);
            }
            if (event.key === 'ArrowRight' && index < boxes.length - 1) {
                event.preventDefault();
                focusBox(index + 1);
            }
        });

        box.addEventListener('paste', (event) => {
            event.preventDefault();
            const digits = (event.clipboardData ? event.clipboardData.getData('text') : '').replace(/\D/g, '').slice(0, 6);
            digits.split('').forEach((digit, i) => {
                if (boxes[i]) {
                    boxes[i].value = digit;
                }
            });
            sync();
            focusBox(Math.min(digits.length, boxes.length - 1));
        });
    });

    focusBox(0);
});

/*
 * Resend cooldown: server provides the epoch second the next resend becomes
 * available; the button stays disabled with a live countdown until that point.
 */
document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-resend-button]');
    if (!button) {
        return;
    }

    const countdown = document.querySelector('[data-resend-countdown]');
    const lockUntil = Number(button.dataset.lockUntil || 0);

    const update = () => {
        const remaining = Math.max(0, lockUntil - Math.floor(Date.now() / 1000));

        if (remaining > 0) {
            button.disabled = true;
            button.textContent = `Resend OTP in ${remaining}s`;
            if (countdown) {
                countdown.textContent = `You can request a new code in ${remaining} seconds.`;
            }
            window.setTimeout(update, 1000);
        } else {
            button.disabled = false;
            button.textContent = 'Resend OTP';
            if (countdown) {
                countdown.textContent = '';
            }
        }
    };

    update();
});