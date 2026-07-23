<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_student();

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$sr_code      = $_SESSION['sr_code'];


$stmt = $pdo->prepare("
    SELECT qt.*, o.name AS office_name
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    WHERE qt.student_id = ?
      AND qt.status NOT IN ('done', 'cancelled', 'completed')
    ORDER BY qt.created_at DESC
    LIMIT 1
");
$stmt->execute([$student_id]);
$active_ticket = $stmt->fetch();

/* OFFICES + CONFIG */
$offices = $pdo->query("
    SELECT
        o.id,
        o.name,
        o.slug,
        o.description,
        oc.start_time,
        oc.end_time
    FROM offices o
    LEFT JOIN office_configs oc ON oc.office_id = o.id
    WHERE o.is_active = 1
")->fetchAll();

/* STATS */
$total_waiting = (int)$pdo->query("
    SELECT COUNT(*) FROM queue_tickets
    WHERE status = 'waiting' AND DATE(created_at) = CURDATE()
")->fetchColumn();

$open_offices = count($offices);

/* Estimate: avg tickets per hour across all offices today (simple heuristic) */
$done_today = (int)$pdo->query("
    SELECT COUNT(*) FROM queue_tickets
    WHERE status = 'done' AND DATE(created_at) = CURDATE()
")->fetchColumn();

$hours_elapsed = max(1, (int)date('H') - 8); // assume office opens 8am
$avg_per_hour  = $done_today > 0 ? round($done_today / $hours_elapsed) : 6;
$est_wait_mins = $avg_per_hour > 0 ? round(($total_waiting / $avg_per_hour) * 60) : null;

$greeting = (int)date('H') < 12 ? 'Good morning' : ((int)date('H') < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniqueue &mdash; Dashboard</title>
    <link rel="stylesheet" href="/assets/css/student-dashboard.css">
    <link rel="stylesheet" href="/assets/css/queue-status.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/footer.css">
</head>
<body class="dashboard-body">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="dashboard-main">

    <!-- ── BANNER ───────────────────────────────────── -->
    <section class="dash-hero">

        <div class="dash-hero__left">
            <div class="dash-hero__badge">
                <span class="dash-hero__badge-dot"></span>
                <?= e($greeting) ?>
            </div>
            <div class="dash-hero__name">
                <?= e(explode(' ', $student_name)[0]) ?> 👋
            </div>
            <div class="dash-hero__code"><?= e($sr_code) ?></div>
        </div>

        <div class="dash-hero__stats">

            <div class="hero-stat">
                <span class="hero-stat__value"><?= $open_offices ?></span>
                <span class="hero-stat__label">Offices Open</span>
            </div>

        </div>

    </section>

    <!-- ── TWO-COLUMN GRID ─────────────────────────────── -->
    <div class="dashboard-grid">

        <!-- LEFT: Your Current Queue -->
        <section class="panel-card">
            <div class="panel-card__header">
                <div class="panel-card__title">Your Current Queue</div>
                <div style="display:flex; align-items:center; gap:0.7rem;">
                    <span class="panel-card__live">
                        <span class="panel-card__live-dot"></span>
                        Live
                    </span>
                    <?php if ($active_ticket): ?>
                       <span 
                        id="dashboard-status-badge"
                        class="ticket-status-badge ticket-status-badge--<?= e($active_ticket['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $active_ticket['status'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-card__body">
                <?php if ($active_ticket): ?>
                    <div class="ticket-box" id="active-ticket-widget" data-ticket-id="<?= (int)$active_ticket['id'] ?>">
                        <div class="active-ticket-card__header">
                            <div class="active-ticket-card__number">
                                #<?= e($active_ticket['queue_number']) ?>
                            </div>
                        </div>
                        <div class="active-ticket-card__office">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <?= e($active_ticket['office_name']) ?>
                        </div>
                        <div class="active-ticket-card__footer">
                            <button
                                type="button"
                                class="btn btn--primary btn--sm"
                                id="trackQueueBtn"
                                data-ticket-id="<?= (int)$active_ticket['id'] ?>">
                                Track Queue
                            </button>
                            <a class="btn btn--outline btn--sm"
                               href="/student/cancel-ticket.php?ticket_id=<?= (int)$active_ticket['id'] ?>"
                               onclick="return confirm('Are you sure you want to cancel your queue?');">
                                Cancel
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ticket-box ticket-box--empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4l2.5 2.5"/>
                        </svg>
                        <span>You don't have an active queue right now.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- RIGHT: Available Offices -->
        <section class="panel-card">
            <div class="panel-card__header">
                <div class="panel-card__title">Available Offices</div>
                <span class="panel-card__count"><?= $open_offices ?> Open</span>
            </div>
            <div class="panel-card__body">
                <?php if ($offices): ?>
                    <div class="offices-list">
                        <?php foreach ($offices as $office): ?>
                        <div class="office-row<?= $active_ticket ? ' office-row--disabled' : '' ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; min-width:0;">
                                <div class="office-row__icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                </div>
                                <div class="office-row__info">
                                    <span class="office-row__name"><?= e($office['name']) ?></span>
                                    <?php if ($active_ticket): ?>
                                        <span class="office-row__note">You already have an active queue</span>
                                    <?php else: ?>
                                        <span class="office-row__hours">
                                            <?= $office['start_time'] ? date('h:i A', strtotime($office['start_time'])) : '08:00 AM' ?>
                                            &ndash;
                                            <?= $office['end_time'] ? date('h:i A', strtotime($office['end_time'])) : '05:00 PM' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($active_ticket): ?>
                                <button type="button" class="btn btn--xs btn--disabled" disabled
                                        title="Finish or cancel your current queue first before joining a new one.">
                                    Join Queue
                                </button>
                            <?php else: ?>
                                <a href="/student/queue.php?office=<?= e($office['slug']) ?>"
                                   class="btn btn--outline btn--xs">
                                    Join Queue
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="offices-list__empty">No offices available right now.</div>
                <?php endif; ?>
            </div>
        </section>

    </div>

</main>
<!-- Queue Status Modal -->
<div id="queueStatusModal" class="queue-modal">
    <div class="queue-modal__backdrop"></div>

    <div class="queue-modal__dialog">

        <button
            type="button"
            class="queue-modal__close"
            id="closeQueueModal">
            &times;
        </button>

        <div
            class="status-card"
            id="queue-status-container">

            <div class="status-card__header">
                <h1 id="office-name">Loading Office...</h1>

                <div
                    id="status-badge"
                    class="ticket-status-badge">
                    ...
                </div>
            </div>

            <div class="status-card__main">

                <div class="queue-number-display">
                    <small>Your Ticket Number</small>
                    <strong id="queue-number">...</strong>
                </div>

                <div
                    id="waiting-info"
                    class="waiting-info">

                    <div class="info-grid">

                        <div class="info-item">
                            <span class="info-label">
                                People Ahead
                            </span>

                            <span
                                class="info-value"
                                id="people-ahead">
                                ...
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">
                                Estimated Wait
                            </span>

                            <span class="info-value">
                                <span id="ewt">...</span> mins
                            </span>
                        </div>

                    </div>

                    <div
                        class="info-item"
                        style="margin-top:1rem;">

                        <span class="info-label">
                            Assigned Counter
                        </span>

                        <span
                            class="info-value"
                            id="assigned-window-name"
                            style="font-size:1.3rem;">
                            ...
                        </span>

                    </div>

                </div>

                <div
                    id="called-info"
                    class="called-info hidden">

                    <div class="called-alert">

                        <div class="called-alert__icon">
                            🔔
                        </div>

                        <h2>Please proceed now!</h2>

                        <p>
                            Go to your assigned service window:
                        </p>

                        <div
                            class="window-name"
                            id="window-name">
                            ...
                        </div>

                    </div>

                </div>

            </div>

            <div class="status-card__footer">

                <p class="text-muted">
                    Last updated:
                    <span id="last-updated">
                        ...
                    </span>
                </p>

                <div class="status-card__actions">

                    <button
                        id="refreshQueueStatus"
                        class="btn btn--outline btn--sm">

                        Refresh

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/dashboard.js"></script>
</body>
</html>