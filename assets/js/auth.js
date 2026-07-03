// assets/js/auth.js — Login form validation, password toggle, spinner, SR-Code AJAX check

(function () {
    'use strict';

    // ── Password visibility toggle ─────────────────────────────────────────────
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    // ── Form submit: show loading spinner ─────────────────────────────────────
    var form = document.getElementById('login-form');
    var submitBtn = document.getElementById('login-btn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            // Basic client-side validation
            var identifier = form.querySelector('#identifier');
            var password   = form.querySelector('#password');
            var hasError   = false;

            [identifier, password].forEach(function (field) {
                if (!field) return;
                if (field.value.trim() === '') {
                    field.classList.add('is-error');
                    hasError = true;
                } else {
                    field.classList.remove('is-error');
                }
            });

            if (hasError) {
                e.preventDefault();
                return;
            }

            // Show spinner
            submitBtn.classList.add('btn--loading');
            submitBtn.disabled = true;
        });
    }

    // ── SR-Code live validation (student tab only) ─────────────────────────────
    var body = document.body;
    if (!body || body.dataset.role !== 'student') return;

    var srInput = document.getElementById('identifier');
    if (!srInput) return;

    // Create status message element
    var statusEl = document.createElement('span');
    statusEl.className = 'srcode-status';
    statusEl.setAttribute('aria-live', 'polite');
    srInput.closest('.form-group').appendChild(statusEl);

    var debounceTimer = null;
    var lastChecked   = '';

    srInput.addEventListener('input', function () {
        var value = srInput.value.trim();

        clearTimeout(debounceTimer);
        statusEl.textContent = '';
        statusEl.className   = 'srcode-status';
        srInput.classList.remove('is-error');

        // Only check if it looks like a complete SR-Code (YY-NNNNN)
        if (!/^\d{2}-\d{5}$/.test(value)) return;
        if (value === lastChecked) return;

        debounceTimer = setTimeout(function () {
            lastChecked = value;
            checkSrCode(value);
        }, 500);
    });

    function checkSrCode(srCode) {
        statusEl.className   = 'srcode-status srcode-status--loading';
        statusEl.textContent = 'Checking…';

        var formData = new FormData();
        formData.append('sr_code', srCode);

        fetch('/auth/validate-srcode.php', {
            method: 'POST',
            body:   formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.valid) {
                statusEl.className   = 'srcode-status srcode-status--valid';
                statusEl.textContent = '✓ ' + data.message;
                srInput.classList.remove('is-error');
            } else {
                statusEl.className   = 'srcode-status srcode-status--invalid';
                statusEl.textContent = '✗ ' + data.message;
                srInput.classList.add('is-error');
            }
        })
        .catch(function () {
            statusEl.className   = 'srcode-status';
            statusEl.textContent = '';
        });
    }
})();
