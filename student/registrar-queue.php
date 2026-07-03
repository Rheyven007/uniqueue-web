<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Appointment dates are plain calendar dates with no timezone info attached.
// "Today" must be computed in the same local timezone the institution operates
// in, or PHP's server default (often UTC) can land on the wrong calendar day.
date_default_timezone_set('Asia/Manila');

require_student();

$student_id = $_SESSION['student_id'];

/* REGISTRAR OFFICE */
$stmt = $pdo->prepare("SELECT id, name FROM offices WHERE slug = 'registrar' LIMIT 1");
$stmt->execute();
$office = $stmt->fetch();

if (!$office) die("Registrar office not found.");

/* DOCUMENTS */
$documents = $pdo->query("SELECT id, name FROM documents")->fetchAll();

/* APPOINTMENT DATES — only dates actually configured on a window that accepts appointments */
$appt_dates_stmt = $pdo->prepare("
    SELECT DISTINCT appointment_date
    FROM windows
    WHERE office_id = ?
      AND queue_type IN ('appointment', 'both')
      AND appointment_date IS NOT NULL
    ORDER BY appointment_date ASC
");
$appt_dates_stmt->execute([$office['id']]);
$appointment_dates = array_map(function ($d) {
    // Normalize whatever format the DB returns (date, datetime, etc.) to Y-m-d
    $ts = strtotime($d);
    return $ts ? date('Y-m-d', $ts) : $d;
}, $appt_dates_stmt->fetchAll(PDO::FETCH_COLUMN));
$appointment_dates = array_values(array_unique($appointment_dates));

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

    $type     = $_POST['type']             ?? null;
    $doc_id   = $_POST['document_id']      ?? null;
    $qty      = $_POST['quantity']         ?? 1;
    $date     = $_POST['appointment_date'] ?? null;
    $priority = isset($_POST['priority'])  ? 1 : 0;
    $reason   = $_POST['priority_reason']  ?? null;

    // Normalize to Y-m-d so comparisons are never thrown off by formatting
    // differences (e.g. a stray time component like "2026-07-01 00:00:00").
    if ($date) {
        $date_ts = strtotime($date);
        $date = $date_ts ? date('Y-m-d', $date_ts) : null;
    }

    if (!$type) {
        $_SESSION['error_message'] = "Please select a queue type.";
        header("Location: /student/registrar-queue.php");
        exit;
    }

    if ($type === 'appointment' && !$date) {
        $_SESSION['error_message'] = "Please select an appointment date.";
        header("Location: /student/registrar-queue.php");
        exit;
    }

    if ($type === 'walkin' && !$doc_id) {
        $_SESSION['error_message'] = "Please select a document.";
        header("Location: /student/registrar-queue.php");
        exit;
    }

    $window_id = null;

    if ($type === 'walkin') {
        // Find a window in this office that's configured to handle this document
        $win_stmt = $pdo->prepare("
            SELECT w.id
            FROM windows w
            JOIN window_document wd ON wd.window_id = w.id
            WHERE w.office_id = ? AND wd.document_id = ?
            ORDER BY w.name ASC
            LIMIT 1
        ");
        $win_stmt->execute([$office['id'], $doc_id]);
        $window_id = $win_stmt->fetchColumn() ?: null;
    }

    if ($type === 'appointment') {
        // Find the window configured to accept appointments on the selected date.
        // DATE() strips any time component so "2026-07-01" matches
        // "2026-07-01 00:00:00" or any other stored variant of the same day.
        $win_stmt = $pdo->prepare("
            SELECT id
            FROM windows
            WHERE office_id = ?
              AND queue_type IN ('appointment', 'both')
              AND DATE(appointment_date) = ?
            ORDER BY name ASC
            LIMIT 1
        ");
        $win_stmt->execute([$office['id'], $date]);
        $window_id = $win_stmt->fetchColumn() ?: null;

        if (!$window_id) {
            $_SESSION['error_message'] = "No counter is currently accepting appointments on that date. Please choose another date.";
            header("Location: /student/registrar-queue.php");
            exit;
        }

        // Students may only join the queue on the actual day of their appointment.
        // Both sides are normalized to Y-m-d above, so this compares calendar
        // days only, never raw strings that might differ in formatting.
        $today = date('Y-m-d');
        if ($date !== $today) {
            $_SESSION['error_message'] = "You can only join the queue on the day of your appointment ("
                . date('F j, Y', strtotime($date)) . "). Please come back on that date.";
            header("Location: /student/registrar-queue.php");
            exit;
        }
    }

    /* QUEUE NUMBER */
    $queue_number = 'Q-' . str_pad(
        (int)$pdo->query("SELECT COUNT(*) + 1 FROM queue_tickets WHERE office_id={$office['id']}")->fetchColumn(),
        4, '0', STR_PAD_LEFT
    );

    /* INSERT TICKET */
    $stmt = $pdo->prepare("
        INSERT INTO queue_tickets
            (student_id, office_id, queue_number, type, status,
             priority, priority_reason, appointment_date, window_id, joined_at, created_at)
        VALUES (?, ?, ?, ?, 'waiting', ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([$student_id, $office['id'], $queue_number, $type, $priority, $reason, $date, $window_id]);

    $ticket_id = $pdo->lastInsertId();

    if ($type === 'walkin') {
        $pdo->prepare("
            INSERT INTO queue_ticket_document (ticket_id, document_id, quantity)
            VALUES (?, ?, ?)
        ")->execute([$ticket_id, $doc_id, $qty]);
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
    <title>Uniqueue &mdash; Registrar Queue</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/registrar-queue.css">
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
        <h1 class="page-title">Registrar Queue</h1>
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
            <div class="wizard-step-connector" id="wizConnBeforeStep3"></div>
            <div class="wizard-step" id="dot3" role="listitem">
                <span class="wizard-step__num">3</span>
                <span class="wizard-step__label">Requirements</span>
            </div>
            <div class="wizard-step-connector" id="wizConnBeforeStep4"></div>
            <div class="wizard-step" id="dot4" role="listitem">
                <span class="wizard-step__num">4</span>
                <span class="wizard-step__label">Quantity</span>
            </div>
            <div class="wizard-step-connector"></div>
            <div class="wizard-step" id="dot5" role="listitem">
                <span class="wizard-step__num" id="dot5Num">5</span>
                <span class="wizard-step__label">Confirm</span>
            </div>
        </div>

        <form method="post" class="wizard-form" id="queueForm">

            <!-- ── STEP 1: TYPE ── -->
            <div class="step active" id="step1">
                <h2 class="step-title">Choose Your Queue Type</h2>
                <p class="step-subtitle">How would you like to transact with the Registrar's Office?</p>

                <div class="type-cards">
                    <label class="type-card" id="typeCardWalkin">
                        <input type="radio" name="type" value="walkin" onclick="setType()" required>
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

                    <label class="type-card" id="typeCardAppt">
                        <input type="radio" name="type" value="appointment" onclick="setType()">
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
                <p class="step-subtitle" id="step2Sub">Fill in the details for your request.</p>

                <!-- APPOINTMENT -->
                <div id="appointmentBox" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Appointment Date</label>

                        <?php if (empty($appointment_dates)): ?>
                            <small class="text-muted">No appointment schedules are currently available. Please check back later.</small>
                        <?php else: ?>
                            <input type="hidden" name="appointment_date" id="appointmentDate" value="">

                            <div class="appt-calendar" id="apptCalendar">
                                <div class="appt-calendar__header">
                                    <button type="button" class="appt-calendar__nav" id="apptPrevMonth" aria-label="Previous month">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2.5"
                                             stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="15 18 9 12 15 6"/>
                                        </svg>
                                    </button>
                                    <span class="appt-calendar__title" id="apptCalendarTitle">&nbsp;</span>
                                    <button type="button" class="appt-calendar__nav" id="apptNextMonth" aria-label="Next month">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2.5"
                                             stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="appt-calendar__weekdays">
                                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                                </div>
                                <div class="appt-calendar__grid" id="apptCalendarGrid"></div>
                                <div class="appt-calendar__legend">
                                    <span class="appt-calendar__legend-item"><i class="appt-dot appt-dot--available"></i> Available</span>
                                    <span class="appt-calendar__legend-item"><i class="appt-dot appt-dot--today"></i> Today</span>
                                    <span class="appt-calendar__legend-item"><i class="appt-dot appt-dot--selected"></i> Selected</span>
                                </div>
                            </div>

                            <div class="appt-calendar__selected-summary" id="apptSelectedSummary">No date selected yet.</div>
                            <div class="alert alert--error" id="apptDateWarning" style="display:none; margin-top:.6rem;"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WALK-IN -->
                <div id="walkinBox" style="display:none;">
                    <div class="form-group">
                        <label class="form-label" for="docSelect">Document Requested</label>
                        <select class="form-control form-select" name="document_id"
                                id="docSelect" onchange="loadRequirements()">
                            <option value="">Select a document&hellip;</option>
                            <?php foreach ($documents as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- PRIORITY LANE — available for both walk-in and appointment
                     queues. Appointment holders (PWD, pregnant, senior citizens)
                     may still need priority handling once they arrive, so this
                     is not limited to walk-in-only requests. -->
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

                <div class="step-actions">
                    <button type="button" class="btn btn--outline" onclick="prevStep()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back
                    </button>
                    <button type="button" class="btn btn--primary" onclick="handleAppointmentStep2Next()">
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

                <div id="requirementsBox" class="requirements-list">
                    <div class="requirements-loading">
                        <div class="loading-spinner"></div>
                        Loading requirements&hellip;
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

            <!-- ── STEP 4: QUANTITY ── -->
            <div class="step" id="step4">
                <h2 class="step-title">Quantity</h2>
                <p class="step-subtitle">How many copies do you need?</p>

                <div class="qty-control">
                    <button type="button" class="qty-btn" id="qtyDown" aria-label="Decrease">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <input type="number" name="quantity" id="qtyInput"
                           class="qty-input" min="1" max="20" value="1" readonly>
                    <button type="button" class="qty-btn" id="qtyUp" aria-label="Increase">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5"  y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
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

            <!-- ── STEP 5: CONFIRM ── -->
            <div class="step" id="step5">
                <h2 class="step-title">Review & Confirm</h2>
                <p class="step-subtitle">Double-check your details before joining the queue.</p>

                <div class="confirm-card">
                    <div class="confirm-row">
                        <span class="confirm-row__label">Queue Type</span>
                        <span class="confirm-row__value" id="cType">&mdash;</span>
                    </div>
                    <div class="confirm-row" id="cDocRow">
                        <span class="confirm-row__label">Document</span>
                        <span class="confirm-row__value" id="cDoc">&mdash;</span>
                    </div>
                    <div class="confirm-row" id="cQtyRow">
                        <span class="confirm-row__label">Quantity</span>
                        <span class="confirm-row__value" id="cQty">&mdash;</span>
                    </div>
                    <div class="confirm-row" id="cDateRow">
                        <span class="confirm-row__label">Date</span>
                        <span class="confirm-row__value" id="cDate">&mdash;</span>
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
    // Data provided by the server for registrar-queue.js to consume.
    // All wizard behavior lives in the external file — this block only
    // exposes the values that come from PHP/the database.
    var APPT_AVAILABLE_DATES = <?= !empty($appointment_dates) ? json_encode($appointment_dates) : '[]' ?>;
    var APPT_TODAY = "<?= date('Y-m-d') ?>";
    <?php if ($active_ticket): ?>
    var ACTIVE_TICKET_ID = <?= (int)$active_ticket['id'] ?>;
    <?php endif; ?>
</script>
<script src="/assets/js/registrar-queue.js"></script>
</body>
</html>