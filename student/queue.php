<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/algo.php';

// Ensure timestamps (joined_at, created_at) are recorded in the
// institution's local timezone rather than the server's default.
date_default_timezone_set('Asia/Manila');

require_student();

$student_id = $_SESSION['student_id'];

/* OFFICE — resolved dynamically from ?office=slug (GET on page load,
   POST on form submit) instead of a hardcoded slug. This one file now
   serves every office; the UI and data queried simply adapt to
   whichever office was requested. */
$office_slug = $_GET['office'] ?? $_POST['office'] ?? null;

if (!$office_slug) {
    $_SESSION['error_message'] = "No office selected.";
    header("Location: /student/dashboard.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT o.id, o.name, o.slug, o.description,
           oc.walkin_enabled, oc.appointment_enabled, oc.priority_enabled
    FROM offices o
    LEFT JOIN office_configs oc ON oc.office_id = o.id
    WHERE o.slug = ? AND o.is_active = 1
    LIMIT 1
");
$stmt->execute([$office_slug]);
$office = $stmt->fetch();

if (!$office) {
    $_SESSION['error_message'] = "That office is not available.";
    header("Location: /student/dashboard.php");
    exit;
}

// Fall back to enabled if office_configs has no row for this office yet.
$walkin_enabled      = $office['walkin_enabled']      === null ? true : (bool)$office['walkin_enabled'];
$appointment_enabled = $office['appointment_enabled'] === null ? true : (bool)$office['appointment_enabled'];
$priority_enabled    = $office['priority_enabled']    === null ? true : (bool)$office['priority_enabled'];

/* DOCUMENTS — scoped to this office only. Used by both queue types now:
   appointment slips only carry name + appointment date, not which
   document is being requested, so appointment students pick documents
   here too, same as walk-in. */
$doc_stmt = $pdo->prepare("SELECT id, name FROM documents WHERE office_id = ?");
$doc_stmt->execute([$office['id']]);
$documents = $doc_stmt->fetchAll();

/* ACTIVE TICKET (for this student, in this office, not yet done/cancelled) */
$active_stmt = $pdo->prepare("
    SELECT qt.id, qt.queue_number, qt.status, qt.type, w.name AS window_name
    FROM queue_tickets qt
    LEFT JOIN windows w ON w.id = qt.window_id
    WHERE qt.student_id = ?
      AND qt.office_id = ?
      AND qt.status IN ('waiting','called','in_progress')
    ORDER BY qt.joined_at DESC
    LIMIT 1
");
$active_stmt->execute([$student_id, $office['id']]);
$active_ticket = $active_stmt->fetch();

/* SUBMIT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type          = $_POST['type']             ?? null;
    $document_ids  = $_POST['document_ids']     ?? [];  // walk-in: 1+ documents
    $doc_qty_input   = $_POST['doc_qty']         ?? [];  // quantity keyed by document_id
    $priority        = isset($_POST['priority']) ? 1 : 0;
    $reason          = $_POST['priority_reason'] ?? null;
    $appt_slip_ok    = isset($_POST['appointment_slip_confirmed']);

    // Clean the submitted document list to a deduped set of ints, then
    // re-verify each one actually belongs to this office — never trust
    // IDs from the client as-is. Both queue types collect documents now:
    // the appointment slip only proves name + appointment date, not which
    // document is being requested, so that still has to be picked here.
    $document_ids = array_values(array_unique(array_filter(array_map('intval', (array)$document_ids))));

    if ($document_ids) {
        $placeholders = implode(',', array_fill(0, count($document_ids), '?'));
        $valid_stmt = $pdo->prepare("SELECT id FROM documents WHERE office_id = ? AND id IN ($placeholders)");
        $valid_stmt->execute(array_merge([$office['id']], $document_ids));
        $document_ids = array_map('intval', $valid_stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // Quantity is per document. Clamp to a sane 1–20 range, default to 1.
    $doc_quantities = [];
    foreach ($document_ids as $did) {
        $q = isset($doc_qty_input[$did]) ? (int)$doc_qty_input[$did] : 1;
        $doc_quantities[$did] = max(1, min(20, $q));
    }

    $redirect_back = "/student/queue.php?office=" . urlencode($office['slug']);

    if (!$type) {
        $_SESSION['error_message'] = "Please select a queue type.";
        header("Location: $redirect_back");
        exit;
    }

    // Re-check against this office's own config — never trust the submitted
    // type alone, since a disabled option could still be POSTed directly.
    if ($type === 'walkin' && !$walkin_enabled) {
        $_SESSION['error_message'] = "Walk-in queueing is not available for this office.";
        header("Location: $redirect_back");
        exit;
    }

    if ($type === 'appointment' && !$appointment_enabled) {
        $_SESSION['error_message'] = "Appointments are not available for this office.";
        header("Location: $redirect_back");
        exit;
    }

    if (!$priority_enabled) {
        $priority = 0;
        $reason   = null;
    }

    if (!$document_ids) {
        $_SESSION['error_message'] = "Please select at least one document.";
        header("Location: $redirect_back");
        exit;
    }

    // Appointment holders are verified via their physical Appointment Slip
    // (not a date picked here), so this checkbox is the one mandatory
    // requirement for that type — re-checked server-side too.
    if ($type === 'appointment' && !$appt_slip_ok) {
        $_SESSION['error_message'] = "Please confirm you have your Appointment Slip.";
        header("Location: $redirect_back");
        exit;
    }

    // A ticket is served at a single window, so the window assigned must
    // be able to handle EVERY requested document at once (e.g. a request
    // for docs 1+2 only qualifies a window that covers both — not one
    // that only covers doc 1). Among windows that qualify, the actual
    // pick is load-based (see includes/algo.php).
    $window_id = pick_best_window($pdo, $office['id'], $document_ids, $type);

    if ($window_id === null) {
        $_SESSION['error_message'] = "No window currently handles this combination of documents. Please contact the office.";
        header("Location: $redirect_back");
        exit;
    }

    /* QUEUE NUMBER */
    $count_stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM queue_tickets WHERE office_id = ?");
    $count_stmt->execute([$office['id']]);
    $queue_number = 'Q-' . str_pad((int)$count_stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);

    /* INSERT TICKET — appointment_date is no longer collected in this
       wizard (verification happens via the physical slip instead), so
       it's left NULL regardless of type. */
    $stmt = $pdo->prepare("
        INSERT INTO queue_tickets
            (student_id, office_id, queue_number, type, status,
             priority, priority_reason, appointment_date, window_id, joined_at, created_at)
        VALUES (?, ?, ?, ?, 'waiting', ?, ?, NULL, ?, NOW(), NOW())
    ");

    $stmt->execute([$student_id, $office['id'], $queue_number, $type, $priority, $reason, $window_id]);

    $ticket_id = $pdo->lastInsertId();

    // One row per requested document — each keeps its own quantity, so a
    // student can request several documents in a single ticket, for
    // either queue type.
    $doc_insert = $pdo->prepare("
        INSERT INTO queue_ticket_document (ticket_id, document_id, quantity)
        VALUES (?, ?, ?)
    ");
    foreach ($document_ids as $did) {
        $doc_insert->execute([$ticket_id, $did, $doc_quantities[$did]]);
    }

    header("Location: /student/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniqueue &mdash; <?= e($office['name']) ?> Queue</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/queue.css">
</head>
<body class="dashboard-body">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="dashboard-main">

    <!-- PAGE TITLE -->
    <div class="page-title-row">
        <a href="/student/dashboard.php" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back
        </a>
        <h1 class="page-title"><?= e($office['name']) ?> Queue</h1>
    </div>

    <?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert--error">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?= e($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <!-- ACTIVE TICKET STATUS -->
    <?php if ($active_ticket): ?>
    <div class="ticket-status-card" id="ticketStatusBox" data-ticket-id="<?= (int)$active_ticket['id'] ?>">
        <div class="ticket-status-card__num"><?= e($active_ticket['queue_number']) ?></div>
        <div class="ticket-status-card__msg" id="ticketStatusMsg">
            <?php if ($active_ticket['status'] === 'waiting'): ?>
                Please wait — you will be called soon.
            <?php elseif (in_array($active_ticket['status'], ['called','in_progress'])): ?>
                Please proceed to <strong><?= e($active_ticket['window_name'] ?? 'the assigned window') ?></strong>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- WIZARD CARD -->
    <div class="wizard-card"<?= $active_ticket ? ' style="display:none;"' : '' ?> id="wizardCard">

        <!-- STEP PROGRESS -->
        <div class="wizard-progress" role="list">
            <div class="wizard-step active" id="dot1" role="listitem" aria-current="step">
                <span class="wizard-step__num">1</span>
                <span class="wizard-step__label">Type</span>
            </div>
            <div class="wizard-step-connector"></div>
            <div class="wizard-step" id="dot2" role="listitem">
                <span class="wizard-step__num">2</span>
                <span class="wizard-step__label">Details</span>
            </div>
            <div class="wizard-step-connector"></div>
            <div class="wizard-step" id="dot3" role="listitem">
                <span class="wizard-step__num">3</span>
                <span class="wizard-step__label">Requirements</span>
            </div>
            <div class="wizard-step-connector"></div>
            <div class="wizard-step" id="dot4" role="listitem">
                <span class="wizard-step__num">4</span>
                <span class="wizard-step__label">Confirm</span>
            </div>
        </div>

        <form method="post" class="wizard-form" id="queueForm">
            <input type="hidden" name="office" value="<?= e($office['slug']) ?>">

            <!-- ── STEP 1: TYPE ── -->
            <div class="step active" id="step1">
                <h2 class="step-title">Choose Your Queue Type</h2>
                <p class="step-subtitle">How would you like to transact with the <?= e($office['name']) ?> office?</p>

                <div class="type-cards">
                    <?php if ($walkin_enabled): ?>
                    <label class="type-card" id="typeCardWalkin">
                        <input type="radio" name="type" value="walkin" onclick="setType()"
                               <?= !$appointment_enabled ? 'checked' : 'required' ?>>
                        <div class="type-card__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="5" r="2"/>
                                <path d="M12 7v5l-3 3m3-3l3 3M9 21l1-5m4 5l-1-5"/>
                            </svg>
                        </div>
                        <div class="type-card__body">
                            <div class="type-card__title">Walk-in</div>
                            <div class="type-card__desc">Join the queue now and transact today</div>
                        </div>
                        <div class="type-card__check">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="3"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </label>
                    <?php endif; ?>

                    <?php if ($appointment_enabled): ?>
                    <label class="type-card" id="typeCardAppt">
                        <input type="radio" name="type" value="appointment" onclick="setType()"
                               <?= !$walkin_enabled ? 'checked required' : '' ?>>
                        <div class="type-card__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8"  y1="2" x2="8"  y2="6"/>
                                <line x1="3"  y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <div class="type-card__body">
                            <div class="type-card__title">Appointment</div>
                            <div class="type-card__desc">Schedule a visit for a future date</div>
                        </div>
                        <div class="type-card__check">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="3"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </label>
                    <?php endif; ?>
                </div>

                <div class="step-actions step-actions--end">
                    <button type="button" class="btn btn--primary" onclick="nextStep()">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── STEP 2: DETAILS ── -->
            <div class="step" id="step2">
                <h2 class="step-title" id="step2Title">Details</h2>
                <p class="step-subtitle" id="step2Sub">Select the document(s) you need and any priority options.</p>

                <!-- DOCUMENTS — shared by both queue types. An appointment
                     slip only proves name + appointment date, not which
                     document is being requested, so appointment students
                     pick documents here too, same as walk-in. -->
                <div class="form-group">
                    <label class="form-label">Documents Requested</label>
                    <p class="text-muted" style="margin:-2px 0 10px;font-size:.85rem;">
                        You may request one or more documents in a single ticket. Set the quantity for each.
                    </p>

                    <div class="doc-multi-list" id="docMultiList">
                        <?php foreach ($documents as $d): ?>
                        <div class="doc-multi-item" data-doc-id="<?= (int)$d['id'] ?>">
                            <label class="doc-multi-item__check">
                                <input type="checkbox" class="doc-checkbox"
                                       name="document_ids[]" value="<?= (int)$d['id'] ?>"
                                       onchange="onDocToggle(this)">
                                <?= e($d['name']) ?>
                            </label>
                            <div class="doc-multi-item__qty" style="display:none;">
                                <button type="button" class="qty-btn qty-btn--sm" data-qty-action="down" aria-label="Decrease">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                                <input type="number" class="qty-input qty-input--sm"
                                       name="doc_qty[<?= (int)$d['id'] ?>]"
                                       min="1" max="20" value="1" readonly>
                                <button type="button" class="qty-btn qty-btn--sm" data-qty-action="up" aria-label="Increase">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5"  y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert--error" id="docSelectWarning" style="display:none; margin-top:.6rem;">
                        Please select at least one document.
                    </div>

                    <!-- Shows which window(s) COULD serve this document
                         combination. This is informational only — the
                         actual window is assigned by the algorithm
                         (includes/algo.php) once the ticket is submitted. -->
                    <div class="possible-windows" id="possibleWindowsBox" style="display:none;"></div>
                </div>

                <!-- PRIORITY LANE — available for both walk-in and appointment
                     queues. Appointment holders (PWD, pregnant, senior citizens)
                     may still need priority handling once they arrive, so this
                     is not limited to walk-in-only requests. Rendered only if
                     this office has priority lane enabled. -->
                <?php if ($priority_enabled): ?>
                <div class="priority-toggle">
                    <label class="toggle-label" for="priorityChk">
                        <div class="toggle-label__text">
                            <span class="toggle-label__title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                Priority Lane
                            </span>
                            <span class="toggle-label__sub">For PWD, pregnant, or senior citizens — applies to walk-in and appointment queues</span>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="priority" id="priorityChk"
                                   onchange="togglePriorityReason()">
                            <span class="toggle-switch__track"></span>
                        </div>
                    </label>
                </div>

                <div class="form-group" id="priorityReasonGroup" style="display:none;">
                    <label class="form-label" for="priorityReason">Reason for Priority</label>
                    <input class="form-control" type="text" name="priority_reason"
                           id="priorityReason" placeholder="e.g. PWD, pregnant, senior citizen">
                </div>
                <?php endif; ?>

                <div class="step-actions">
                    <button type="button" class="btn btn--outline" onclick="prevStep()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back
                    </button>
                    <button type="button" class="btn btn--primary" onclick="nextStep()">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── STEP 3: REQUIREMENTS ── -->
            <div class="step" id="step3">
                <h2 class="step-title">Requirements</h2>
                <p class="step-subtitle">Please confirm you have the following ready.</p>

                <!-- WALK-IN: union of requirements across every selected document,
                     loaded via AJAX and grouped per document. -->
                <div id="requirementsBox" class="requirements-list" style="display:none;">
                    <div class="requirements-loading">
                        <div class="loading-spinner"></div>
                        Loading requirements&hellip;
                    </div>
                </div>

                <!-- APPOINTMENT: always exactly one mandatory requirement,
                     regardless of what the appointment is for. -->
                <div id="apptRequirementBox" class="requirements-list" style="display:none;">
                    <div class="req-group">
                        <div class="req-group__title">Appointment</div>
                        <label style="display:block;margin:5px 0;">
                            <input type="checkbox" class="req-check" id="apptSlipCheck"
                                   name="appointment_slip_confirmed" value="1">
                            Bring your Appointment Slip / confirmation to present at the window.
                        </label>
                    </div>
                </div>

                <div class="step-actions">
                    <button type="button" class="btn btn--outline" onclick="prevStep()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back
                    </button>
                    <button type="button" class="btn btn--primary" onclick="nextStep()">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            </div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5"  y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                </div>

            <!-- ── STEP 4: CONFIRM ── -->
            <div class="step" id="step4">
                <h2 class="step-title">Review & Confirm</h2>
                <p class="step-subtitle">Double-check your details before joining the queue.</p>

                <div class="confirm-card">
                    <div class="confirm-row">
                        <span class="confirm-row__label">Queue Type</span>
                        <span class="confirm-row__value" id="cType">&mdash;</span>
                    </div>
                    <div class="confirm-row confirm-row--docs">
                        <span class="confirm-row__label">Documents</span>
                        <span class="confirm-row__value" id="cDocList">&mdash;</span>
                    </div>
                    <div class="confirm-row">
                        <span class="confirm-row__label">Priority Lane</span>
                        <span class="confirm-row__value" id="cPriority">&mdash;</span>
                    </div>
                    <div class="confirm-row" id="cPriorityReasonRow" style="display:none;">
                        <span class="confirm-row__label">Priority Reason</span>
                        <span class="confirm-row__value" id="cPriorityReason">&mdash;</span>
                    </div>
                </div>

                <div class="step-actions">
                    <button type="button" class="btn btn--outline" onclick="prevStep()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back
                    </button>
                    <button type="submit" class="btn btn--primary btn--confirm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Confirm &amp; Join Queue
                    </button>
                </div>
            </div>

        </form>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
    // Data provided by the server for queue.js to consume.
    // All wizard behavior lives in the external file — this block only
    // exposes the values that come from PHP/the database. queue.js is now
    // shared across every office; OFFICE_SLUG lets it know which office
    // it's operating on (e.g. for building the get-requirements.php URL
    // if that endpoint is ever scoped per office).
    var OFFICE_SLUG = <?= json_encode($office['slug']) ?>;
    var OFFICE_ID = <?= (int)$office['id'] ?>; // used by loadPossibleWindows() in queue.js
    var WALKIN_ENABLED = <?= $walkin_enabled ? 'true' : 'false' ?>;
    var APPOINTMENT_ENABLED = <?= $appointment_enabled ? 'true' : 'false' ?>;
    <?php if ($active_ticket): ?>
    var ACTIVE_TICKET_ID = <?= (int)$active_ticket['id'] ?>;
    <?php endif; ?>
</script>
<script src="/assets/js/queue.js"></script>
</body>
</html>