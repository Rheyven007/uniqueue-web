
let currentStep = 1;
let selectedType = null;
const TOTAL_STEPS = 4; // 1 Type, 2 Details, 3 Requirements, 4 Confirm

/* ── Step navigation ─────────────────────────────────────────── */

function setType() {
    selectedType = document.querySelector('input[name="type"]:checked')?.value;

    document.querySelectorAll('.type-card').forEach(card => {
        card.classList.remove('selected');

        const radio = card.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            card.classList.add('selected');
        }
    });

    const docsSection = document.querySelector('#docMultiList')?.closest('.form-group');
    const windowsBox = document.getElementById('possibleWindowsBox');

    if (docsSection) docsSection.style.display = '';

if (selectedType === 'appointment') {
    loadPossibleWindows();
} 
}

function showStep(step) {
    if (step < 1 || step > TOTAL_STEPS) return;

    currentStep = step;

    // Toggle step panels
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    const panel = document.getElementById('step' + step);
    if (panel) panel.classList.add('active');

    // Update progress indicators
    const dots = document.querySelectorAll('.wizard-step');
    dots.forEach((dot, i) => {
        const num = i + 1;
        dot.classList.remove('active', 'completed');
        if (num < step)      dot.classList.add('completed');
        else if (num === step) dot.classList.add('active');
    });

    if (step === 3) enterRequirementsStep();
    if (step === TOTAL_STEPS) updateSummary();

    // Scroll wizard card into view on mobile
    const card = document.querySelector('.wizard-card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function nextStep() {

    if (currentStep === 1) {
        if (!selectedType) {
            showFieldError("Please select a queue type.");
            return;
        }
        return showStep(2);
    }

    if (currentStep === 2) {

        const selectedDocs = getSelectedDocIds();

        if (selectedDocs.length === 0) {
            document.getElementById('docSelectWarning').style.display = 'block';
            showFieldError("Please select at least one document.");
            return;
        }

        document.getElementById('docSelectWarning').style.display = 'none';

        return showStep(3);
    }

    if (currentStep === 3) {

        // Document requirements are required for BOTH Walk-in and Appointment
        const checks = document.querySelectorAll('#requirementsBox input[type="checkbox"]');

        if (checks.length === 0) {
            showFieldError("Requirements could not be loaded.");
            return;
        }

        const allChecked = [...checks].every(c => c.checked);

        if (!allChecked) {
            showFieldError("Please confirm all requirements are met.");
            return;
        }

        // Appointment additionally requires the Appointment Slip
        if (selectedType === 'appointment') {
            const slip = document.getElementById('apptSlipCheck');

            if (!slip || !slip.checked) {
                showFieldError("Please confirm you'll bring your Appointment Slip.");
                return;
            }
        }

        return showStep(4);
    }
}       

function prevStep() {
    if (currentStep <= 1) return;
    showStep(currentStep - 1);
}

/* ── Document multi-select (walk-in) ─────────────────────────── */

function getSelectedDocIds() {
    return [...document.querySelectorAll('.doc-checkbox:checked')].map(c => c.value);
}

function onDocToggle(checkbox) {
    const item = checkbox.closest('.doc-multi-item');
    const qtyBox = item?.querySelector('.doc-multi-item__qty');
    if (qtyBox) qtyBox.style.display = checkbox.checked ? 'flex' : 'none';

    const warning = document.getElementById('docSelectWarning');
    if (warning && checkbox.checked) warning.style.display = 'none';

    loadPossibleWindows();
}

function loadPossibleWindows() {
    const box = document.getElementById('possibleWindowsBox');
    if (!box) return;

    const docIds = getSelectedDocIds();
    if (docIds.length === 0 || typeof OFFICE_ID === 'undefined') {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    box.style.display = 'block';
    box.innerHTML = '<span class="text-muted" style="font-size:.85rem;">Checking possible windows&hellip;</span>';

    fetch('/student/get-possible-windows.php?office_id=' + encodeURIComponent(OFFICE_ID)
        + '&doc_ids=' + encodeURIComponent(docIds.join(','))
        + '&type=' + encodeURIComponent(selectedType || 'walkin'))
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.windows.length) {
                box.innerHTML = '<span class="text-muted" style="font-size:.85rem;">No window currently handles this combination.</span>';
                return;
            }
            const names = data.windows.map(w => w.name).join(', ');
            box.innerHTML = '<span style="font-size:.85rem;">Possible window(s): <strong>' + names + '</strong></span>';
        })
        .catch(() => {
            box.innerHTML = '';
            box.style.display = 'none';
        });
}

// Event delegation for the per-document quantity +/- buttons, since
// documents are rendered dynamically per office.
(function initDocQtyStepper() {
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('docMultiList');
        if (!list) return;

        list.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-qty-action]');
            if (!btn || !list.contains(btn)) return;

            const item = btn.closest('.doc-multi-item');
            const input = item?.querySelector('.qty-input--sm');
            if (!input) return;

            const min = parseInt(input.min, 10) || 1;
            const max = parseInt(input.max, 10) || 20;
            const val = parseInt(input.value, 10) || 1;

            if (btn.dataset.qtyAction === 'up' && val < max) input.value = val + 1;
            if (btn.dataset.qtyAction === 'down' && val > min) input.value = val - 1;
        });
    });
})();

/* ── Requirements ────────────────────────────────────────────── */

function enterRequirementsStep() {

    const reqBox = document.getElementById('requirementsBox');
    const apptBox = document.getElementById('apptRequirementBox');

    // Always show document requirements
    if (reqBox) reqBox.style.display = 'block';
    loadRequirements();

    // Appointment only adds the Appointment Slip requirement
    if (selectedType === 'appointment') {
        if (apptBox) apptBox.style.display = 'block';
    } else {
        if (apptBox) apptBox.style.display = 'none';
    }
}

function loadRequirements() {
    const docIds = getSelectedDocIds();
    const box = document.getElementById('requirementsBox');
    if (!box) return;

    if (docIds.length === 0) {
        box.innerHTML = '<p style="color:var(--red);font-size:.88rem;">No documents selected.</p>';
        return;
    }

    box.innerHTML = '<div class="requirements-loading"><div class="loading-spinner"></div>Loading requirements&hellip;</div>';

    fetch('/student/get-requirements.php?doc_ids=' + encodeURIComponent(docIds.join(',')))
        .then(res => res.text())
        .then(html => {
            box.innerHTML = html || '<p style="color:var(--ink-light);font-size:.88rem;">No specific requirements listed.</p>';
        })
        .catch(() => {
            box.innerHTML = '<p style="color:var(--red);font-size:.88rem;">Could not load requirements. Please continue.</p>';
        });
}

/* ── Priority reason toggle ──────────────────────────────────── */

function togglePriorityReason() {
    const isChecked = document.getElementById('priorityChk').checked;
    const group     = document.getElementById('priorityReasonGroup');
    if (group) {
        group.style.display = isChecked ? 'block' : 'none';
        if (isChecked) group.querySelector('input')?.focus();
    }
}

/* ── Summary ─────────────────────────────────────────────────── */

function updateSummary() {
    const isWalkin = selectedType === 'walkin';
    const typeLabel = isWalkin ? 'Walk-in' : 'Appointment';
    setText('cType', typeLabel);

    const docLines = [...document.querySelectorAll('.doc-multi-item')]
        .filter(item => item.querySelector('.doc-checkbox')?.checked)
        .map(item => {
            const name = item.querySelector('.doc-multi-item__check')?.textContent.trim();
            const qty  = item.querySelector('.qty-input--sm')?.value || '1';
            return name + ' (x' + qty + ')';
        });
    const row = document.querySelector('.confirm-row--docs');

    if (row) row.style.display = '';

    setText('cDocList', docLines.length ? docLines.join(', ') : 'N/A');

        const priority = document.getElementById('priorityChk')?.checked ? 'Yes' : 'No';
        setText('cPriority', priority);
    }

/* ── Utilities ───────────────────────────────────────────────── */

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '—';
}

function setRowVisible(id, visible) {
    const el = document.getElementById(id);
    if (el) el.style.display = visible ? '' : 'none';
}

function showFieldError(message) {
    // Remove any existing transient alerts
    document.querySelectorAll('.alert--transient').forEach(el => el.remove());

    const alert = document.createElement('div');
    alert.className = 'alert alert--error alert--transient';
    alert.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        ${message}
    `;

    const activeStep = document.querySelector('.step.active');
    const actions    = activeStep?.querySelector('.step-actions');
    if (actions) {
        activeStep.insertBefore(alert, actions);
    }

    // Auto-dismiss after 4s
    setTimeout(() => alert.remove(), 4000);
}

/* ── Active ticket status polling ───────────────────────────── */

function pollTicketStatus(ticketId) {
    fetch('/student/get-ticket-status.php?ticket_id=' + encodeURIComponent(ticketId))
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const t = data.ticket;
            const msgEl = document.getElementById('ticketStatusMsg');
            if (!msgEl) return;

            if (t.status === 'waiting') {
                msgEl.innerHTML = 'Please wait — you will be called soon.';
            } else if (t.status === 'called' || t.status === 'in_progress') {
                msgEl.innerHTML = 'Please proceed to <strong>' + (t.window_name || 'the assigned window') + '</strong>';
            } else if (t.status === 'done' || t.status === 'cancelled') {
                // Ticket finished — stop polling and reload to show the wizard again
                location.reload();
                return;
            }

            setTimeout(() => pollTicketStatus(ticketId), 5000);
        })
        .catch(() => {
            setTimeout(() => pollTicketStatus(ticketId), 8000);
        });
}

/* ── Priority Lane confirm-step sync ─────────────────────────── */
/* Keeps the Review & Confirm step's Priority Lane summary in sync with
   the toggle in Step 2, regardless of the step-navigation logic above.
   Re-runs whenever the Confirm step becomes active, and also live-updates
   on any change so it's never stale. */

function initPriorityConfirmSync() {
    var priorityChk     = document.getElementById('priorityChk');
    var priorityReason  = document.getElementById('priorityReason');
    var cPriority        = document.getElementById('cPriority');
    var cPriorityRow     = document.getElementById('cPriorityReasonRow');
    var cPriorityReason  = document.getElementById('cPriorityReason');
    var confirmStep       = document.getElementById('step' + TOTAL_STEPS);

    if (!priorityChk || !cPriority || !cPriorityRow || !cPriorityReason) return;

    function updatePriorityConfirm() {
        var isPriority = priorityChk.checked;
        cPriority.textContent = isPriority ? 'Yes' : 'No';

        if (isPriority) {
            var reasonText = priorityReason && priorityReason.value.trim() !== ''
                ? priorityReason.value.trim()
                : '\u2014';
            cPriorityReason.textContent = reasonText;
            cPriorityRow.style.display = '';
        } else {
            cPriorityRow.style.display = 'none';
        }
    }

    priorityChk.addEventListener('change', updatePriorityConfirm);
    if (priorityReason) {
        priorityReason.addEventListener('input', updatePriorityConfirm);
    }

    // Catch the case where the wizard's own script writes "Yes"/"No" into
    // #cPriority when the Confirm step becomes active — re-sync right after.
    if (confirmStep && window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.attributeName === 'class' && confirmStep.classList.contains('active')) {
                    updatePriorityConfirm();
                }
            });
        });
        observer.observe(confirmStep, { attributes: true });
    }

    updatePriorityConfirm();
}

/* ── Init on load ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    showStep(1);
    initPriorityConfirmSync();

    if (typeof ACTIVE_TICKET_ID !== 'undefined') {
        pollTicketStatus(ACTIVE_TICKET_ID);
    }
});