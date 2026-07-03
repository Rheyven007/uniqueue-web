// assets/js/registrar-queue.js — Registrar Queue Wizard

let currentStep = 1;
let selectedType = null;
const TOTAL_STEPS = 5;

/* ── Step navigation ─────────────────────────────────────────── */

function setType() {
    selectedType = document.querySelector('input[name="type"]:checked')?.value;

    if (selectedType === 'walkin') {
        document.getElementById('step2Title').innerText = 'Walk-in Details';
        document.getElementById('step2Sub').innerText   = 'Select the document you need and any priority options.';
        document.getElementById('walkinBox').style.display     = 'block';
        document.getElementById('appointmentBox').style.display = 'none';
    } else {
        document.getElementById('step2Title').innerText = 'Appointment Details';
        document.getElementById('step2Sub').innerText   = 'Choose a date for your appointment.';
        document.getElementById('walkinBox').style.display     = 'none';
        document.getElementById('appointmentBox').style.display = 'block';
    }

    updateProgressForType(selectedType);

    // Visually highlight selected type card
    document.querySelectorAll('.type-card').forEach(card => card.classList.remove('selected'));
}

/* Show only the steps relevant to the selected queue type in the progress
   stepper. Walk-in uses all 5 steps (Type, Details, Requirements, Quantity,
   Confirm). Appointment skips Requirements and Quantity entirely, so the
   stepper collapses to 3 steps (Type, Details, Confirm) and the Confirm
   dot is renumbered to match. */
function updateProgressForType(type) {
    const isAppointment = type === 'appointment';
    const hiddenEls = [
        document.getElementById('wizConnBeforeStep3'),
        document.getElementById('dot3'),
        document.getElementById('wizConnBeforeStep4'),
        document.getElementById('dot4'),
    ];

    hiddenEls.forEach(el => {
        if (el) el.style.display = isAppointment ? 'none' : '';
    });

    const dot5Num = document.getElementById('dot5Num');
    if (dot5Num) dot5Num.textContent = isAppointment ? '3' : '5';
}

function showStep(step) {
    if (step < 1 || step > TOTAL_STEPS) return;

    currentStep = step;

    // Toggle step panels
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    const panel = document.getElementById('step' + step);
    if (panel) panel.classList.add('active');

    // Update progress indicators
    document.querySelectorAll('.wizard-step').forEach((dot, i) => {
        const stepNum = i / 2 + 1; // account for connectors in DOM
        dot.classList.remove('active', 'completed');
    });

    // Re-select dots (they are every other child due to connectors)
    const dots = document.querySelectorAll('.wizard-step');
    dots.forEach((dot, i) => {
        const num = i + 1;
        if (num < step)      dot.classList.add('completed');
        else if (num === step) dot.classList.add('active');
    });

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
        if (selectedType === 'appointment') {
            const date = document.querySelector("input[name='appointment_date']").value;
            if (!date) { showFieldError("Please select an appointment date."); return; }
            return showStep(TOTAL_STEPS); // skip steps 3 & 4 for appointments
        }

        const doc = document.getElementById('docSelect').value;
        if (!doc) { showFieldError("Please select a document."); return; }
        return showStep(3);
    }

    if (currentStep === 3) {
        const checks = document.querySelectorAll('#requirementsBox input[type="checkbox"]');
        if (checks.length === 0) { showFieldError("Requirements could not be loaded. Please try again."); return; }
        const allChecked = [...checks].every(c => c.checked);
        if (!allChecked) { showFieldError("Please confirm all requirements are met."); return; }
        return showStep(4);
    }

    if (currentStep === 4) return showStep(TOTAL_STEPS);
}

function prevStep() {
    if (currentStep <= 1) return;

    // If going back from confirm (step 5), skip steps 3 & 4 for appointments
    if (currentStep === TOTAL_STEPS && selectedType === 'appointment') {
        return showStep(2);
    }

    showStep(currentStep - 1);
}

/* ── Requirements ────────────────────────────────────────────── */

function loadRequirements() {
    const doc = document.getElementById('docSelect').value;
    const box = document.getElementById('requirementsBox');
    if (!doc) return;

    box.innerHTML = '<div class="requirements-loading"><div class="loading-spinner"></div>Loading requirements&hellip;</div>';

    fetch('/student/get-requirements.php?doc_id=' + encodeURIComponent(doc))
        .then(res => res.text())
        .then(html => {
            box.innerHTML = html || '<p style="color:var(--ink-light);font-size:.88rem;">No specific requirements listed for this document.</p>';
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

/* ── Quantity stepper ────────────────────────────────────────── */

(function initQtyStepper() {
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('qtyInput');
        const up    = document.getElementById('qtyUp');
        const down  = document.getElementById('qtyDown');
        if (!input || !up || !down) return;

        up.addEventListener('click', function () {
            const max = parseInt(input.max, 10) || 20;
            const val = parseInt(input.value, 10) || 1;
            if (val < max) input.value = val + 1;
        });

        down.addEventListener('click', function () {
            const min = parseInt(input.min, 10) || 1;
            const val = parseInt(input.value, 10) || 1;
            if (val > min) input.value = val - 1;
        });
    });
})();

/* ── Summary ─────────────────────────────────────────────────── */

function updateSummary() {
    const isWalkin = selectedType === 'walkin';

    const typeLabel = isWalkin ? 'Walk-in' : 'Appointment';
    setText('cType', typeLabel);

    const docEl = document.getElementById('docSelect');
    const docName = docEl?.value ? docEl.selectedOptions[0]?.text : 'N/A';
    setText('cDoc', docName);
    setRowVisible('cDocRow', isWalkin);

    const qty = document.getElementById('qtyInput')?.value || '1';
    setText('cQty', qty);
    setRowVisible('cQtyRow', isWalkin);

    const date = document.querySelector('input[name="appointment_date"]')?.value || 'N/A';
    setText('cDate', date);
    setRowVisible('cDateRow', !isWalkin);

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

/* ── Appointment calendar ────────────────────────────────────── */
/* Moved from an inline <script> block in registrar-queue.php.
   Relies on the globals APPT_AVAILABLE_DATES and APPT_TODAY, which
   the page sets from server data before this file is loaded. */

function initApptCalendar() {
    var grid = document.getElementById('apptCalendarGrid');
    if (!grid) return; // no appointment schedules configured — calendar not rendered

    var titleEl = document.getElementById('apptCalendarTitle');
    var hiddenInput = document.getElementById('appointmentDate');
    var summaryEl = document.getElementById('apptSelectedSummary');
    var warningEl = document.getElementById('apptDateWarning');
    var prevBtn = document.getElementById('apptPrevMonth');
    var nextBtn = document.getElementById('apptNextMonth');

    var available = (APPT_AVAILABLE_DATES || []).slice().sort();
    var availableSet = {};
    available.forEach(function (d) { availableSet[d] = true; });

    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];

    function parseISO(s) {
        var parts = s.split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }
    function toISO(d) {
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var day = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + day;
    }
    function displayDate(iso) {
        var d = parseISO(iso);
        return monthNames[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    var minDate = available.length ? parseISO(available[0]) : null;
    var maxDate = available.length ? parseISO(available[available.length - 1]) : null;

    // Open on the month containing today if it has an available date, otherwise the earliest available month.
    var initial = minDate || parseISO(APPT_TODAY);
    if (availableSet[APPT_TODAY]) initial = parseISO(APPT_TODAY);
    var viewYear = initial.getFullYear();
    var viewMonth = initial.getMonth();

    function render() {
        titleEl.textContent = monthNames[viewMonth] + ' ' + viewYear;
        grid.innerHTML = '';

        var firstOfMonth = new Date(viewYear, viewMonth, 1);
        var startWeekday = firstOfMonth.getDay();
        var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

        for (var i = 0; i < startWeekday; i++) {
            var blank = document.createElement('span');
            blank.className = 'appt-calendar__day appt-calendar__day--empty';
            grid.appendChild(blank);
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var iso = toISO(new Date(viewYear, viewMonth, day));
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'appt-calendar__day';
            btn.textContent = day;

            if (availableSet[iso]) {
                btn.classList.add('appt-calendar__day--available');
                btn.addEventListener('click', (function (iso) {
                    return function () { selectDate(iso); };
                })(iso));
            } else {
                btn.classList.add('appt-calendar__day--disabled');
                btn.disabled = true;
            }
            if (iso === APPT_TODAY) btn.classList.add('appt-calendar__day--today');
            if (hiddenInput.value === iso) btn.classList.add('appt-calendar__day--selected');

            grid.appendChild(btn);
        }

        prevBtn.disabled = !minDate || (viewYear < minDate.getFullYear() ||
            (viewYear === minDate.getFullYear() && viewMonth <= minDate.getMonth()));
        nextBtn.disabled = !maxDate || (viewYear > maxDate.getFullYear() ||
            (viewYear === maxDate.getFullYear() && viewMonth >= maxDate.getMonth()));
    }

    function selectDate(iso) {
        hiddenInput.value = iso;
        warningEl.style.display = 'none';

        if (iso === APPT_TODAY) {
            summaryEl.textContent = 'Selected: ' + displayDate(iso) + ' — you can join the queue now.';
            summaryEl.className = 'appt-calendar__selected-summary appt-calendar__selected-summary--ok';
        } else {
            summaryEl.textContent = 'Selected: ' + displayDate(iso);
            summaryEl.className = 'appt-calendar__selected-summary';
            warningEl.textContent = 'You can only join the queue on the day of your appointment ('
                + displayDate(iso) + '). You can review your request, but you won\'t be able to join the queue until that date.';
            warningEl.style.display = 'block';
        }
        render();
    }

    prevBtn.addEventListener('click', function () {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        render();
    });
    nextBtn.addEventListener('click', function () {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        render();
    });

    render();
}

function handleAppointmentStep2Next() {
    var typeInput = document.querySelector('input[name="type"]:checked');
    var type = typeInput ? typeInput.value : null;

    if (type === 'appointment') {
        var hidden = document.getElementById('appointmentDate');
        var warningEl = document.getElementById('apptDateWarning');

        if (!hidden) {
            // No appointment schedules configured — nothing to select, cannot proceed
            return;
        }

        var val = hidden.value;
        if (!val) {
            if (warningEl) {
                warningEl.textContent = 'Please select an appointment date from the calendar.';
                warningEl.style.display = 'block';
            }
            return;
        }
        if (val !== APPT_TODAY) {
            if (warningEl) {
                warningEl.textContent = 'You can only join the queue on the day of your appointment. Please come back on that date.';
                warningEl.style.display = 'block';
            }
            return;
        }
    }

    if (typeof nextStep === 'function') nextStep();
}

/* ── Priority Lane confirm-step sync ─────────────────────────── */
/* Keeps the Review & Confirm step's Priority Lane summary in sync with
   the toggle in Step 2, regardless of the step-navigation logic above.
   Re-runs whenever Step 5 becomes active, and also live-updates on any
   change so it's never stale. */

function initPriorityConfirmSync() {
    var priorityChk     = document.getElementById('priorityChk');
    var priorityReason  = document.getElementById('priorityReason');
    var cPriority        = document.getElementById('cPriority');
    var cPriorityRow     = document.getElementById('cPriorityReasonRow');
    var cPriorityReason  = document.getElementById('cPriorityReason');
    var step5            = document.getElementById('step5');

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
    // #cPriority when Step 5 becomes active — re-sync right after.
    if (step5 && window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.attributeName === 'class' && step5.classList.contains('active')) {
                    updatePriorityConfirm();
                }
            });
        });
        observer.observe(step5, { attributes: true });
    }

    updatePriorityConfirm();
}

/* ── Init on load ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    showStep(1);
    initApptCalendar();
    initPriorityConfirmSync();

    if (typeof ACTIVE_TICKET_ID !== 'undefined') {
        pollTicketStatus(ACTIVE_TICKET_ID);
    }
});