// assets/js/signup.js — Signup form: password strength, confirm match, college→program cascade

(function () {
    'use strict';

    // ── College → Program cascading select ─────────────────────────────────────
    var collegeSelect = document.getElementById('college_id');
    var programSelect = document.getElementById('program_id');
    var programs = window.__PROGRAMS__ || [];
    var oldProgramId = window.__OLD_PROGRAM__ || null;

    function populatePrograms(collegeId, selectedId) {
        if (!programSelect) return;

        programSelect.innerHTML = '';

        if (!collegeId) {
            programSelect.disabled = true;
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select college first';
            placeholder.selected = true;
            programSelect.appendChild(placeholder);
            return;
        }

        var matches = programs.filter(function (p) {
            return String(p.college_id) === String(collegeId);
        });

        var firstOption = document.createElement('option');
        firstOption.value = '';
        firstOption.textContent = matches.length ? 'Select program' : 'No programs available';
        firstOption.disabled = true;
        firstOption.selected = !selectedId;
        programSelect.appendChild(firstOption);

        matches.forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.label;
            if (selectedId && String(selectedId) === String(p.id)) {
                opt.selected = true;
            }
            programSelect.appendChild(opt);
        });

        programSelect.disabled = matches.length === 0;
    }

    if (collegeSelect) {
        // Initialize on load (handles validation re-render with old values)
        populatePrograms(collegeSelect.value, oldProgramId);

        collegeSelect.addEventListener('change', function () {
            populatePrograms(collegeSelect.value, null);
        });
    }

    // ── SR-Code: keep it clean while typing (digits + one dash) ────────────────
    var srInput = document.getElementById('sr_code');
    if (srInput) {
        srInput.addEventListener('input', function () {
            var digits = srInput.value.replace(/[^0-9]/g, '').slice(0, 7);
            if (digits.length > 2) {
                srInput.value = digits.slice(0, 2) + '-' + digits.slice(2);
            } else {
                srInput.value = digits;
            }
        });
    }

    // ── Live password strength meter ────────────────────────────────────────
    var passwordInput = document.getElementById('password');
    var fill  = document.getElementById('pw-strength-fill');
    var label = document.getElementById('pw-strength-label');
    var reqList = document.getElementById('pw-requirements');

    var rules = {
        length:  function (v) { return v.length >= 8; },
        lower:   function (v) { return /[a-z]/.test(v); },
        upper:   function (v) { return /[A-Z]/.test(v); },
        number:  function (v) { return /[0-9]/.test(v); },
        special: function (v) { return /[^a-zA-Z0-9]/.test(v); },
    };

    var levels = [
        { min: 0, key: null,           text: '' },
        { min: 1, key: 'weak',         text: 'Weak' },
        { min: 2, key: 'fair',         text: 'Fair' },
        { min: 3, key: 'good',         text: 'Good' },
        { min: 4, key: 'strong',       text: 'Strong' },
        { min: 5, key: 'very-strong',  text: 'Very strong' },
    ];

    function evaluatePassword(value) {
        var metCount = 0;
        var metKeys = {};

        Object.keys(rules).forEach(function (key) {
            var met = value.length > 0 && rules[key](value);
            metKeys[key] = met;
            if (met) metCount++;
        });

        // Bonus: longer passwords bump strength even if all 5 rules already met
        if (metCount === 5 && value.length >= 12) {
            metCount = 5; // stays at max level; length already required for "very-strong" feel
        }

        return { metCount: value.length === 0 ? 0 : metCount, metKeys: metKeys };
    }

    function renderStrength(value) {
        var result = evaluatePassword(value);
        var level = levels[result.metCount] || levels[0];

        if (fill) {
            if (level.key) {
                fill.setAttribute('data-level', level.key);
            } else {
                fill.removeAttribute('data-level');
                fill.style.width = '0%';
            }
        }

        if (label) {
            if (level.key) {
                label.setAttribute('data-level', level.key);
                label.textContent = level.text;
            } else {
                label.removeAttribute('data-level');
                label.innerHTML = '&nbsp;';
            }
        }

        if (reqList) {
            Object.keys(result.metKeys).forEach(function (key) {
                var li = reqList.querySelector('[data-rule="' + key + '"]');
                if (!li) return;
                li.classList.toggle('met', result.metKeys[key]);
            });
        }

        return result;
    }

    if (passwordInput) {
        renderStrength(passwordInput.value);
        passwordInput.addEventListener('input', function () {
            renderStrength(passwordInput.value);
            checkConfirmMatch();
        });
    }

    // ── Confirm password live match ─────────────────────────────────────────
    var confirmInput = document.getElementById('confirm_password');
    var matchEl = document.getElementById('confirm-match');

    function checkConfirmMatch() {
        if (!confirmInput || !matchEl || !passwordInput) return;

        if (confirmInput.value === '') {
            matchEl.removeAttribute('data-state');
            matchEl.innerHTML = '&nbsp;';
            return;
        }

        if (confirmInput.value === passwordInput.value) {
            matchEl.setAttribute('data-state', 'match');
            matchEl.textContent = '✓ Passwords match';
        } else {
            matchEl.setAttribute('data-state', 'mismatch');
            matchEl.textContent = '✗ Passwords do not match';
        }
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', checkConfirmMatch);
    }

    // ── Block submit on weak password / mismatch (client-side guard) ───────────
    var form = document.getElementById('signup-form');
    var submitBtn = document.getElementById('signup-btn');

    if (form) {
        form.addEventListener('submit', function (e) {
            var result = passwordInput ? evaluatePassword(passwordInput.value) : { metCount: 5 };
            var mismatch = confirmInput && passwordInput && confirmInput.value !== passwordInput.value;

            if (result.metCount < 5) {
                e.preventDefault();
                renderStrength(passwordInput.value);
                if (label) label.textContent = 'Password does not meet all requirements';
                return;
            }

            if (mismatch) {
                e.preventDefault();
                checkConfirmMatch();
                return;
            }

            if (submitBtn) {
                submitBtn.classList.add('btn--loading');
                submitBtn.disabled = true;
            }
        });
    }
})();