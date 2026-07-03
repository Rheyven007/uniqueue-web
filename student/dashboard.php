<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_student();

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$sr_code      = $_SESSION['sr_code'];

/* ACTIVE TICKET
   Appointment tickets no longer carry a future appointment_date (that
   field isn't collected in the wizard anymore — verification happens via
   the physical Appointment Slip instead), so both queue types are now
   found the same way: any of this student's tickets that hasn't reached
   a terminal status yet. */
$stmt = $pdo->prepare("
    SELECT qt.*, o.name AS office_name
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    WHERE qt.student_id = ?
      AND qt.status NOT IN ('done','cancelled')
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniqueue &mdash; Dashboard</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/header.css">
</head>
<body class="dashboard-body">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="dashboard-main">

    <!-- ── HERO ─────────────────────────────────────── -->
    <section class="dash-hero">

        <div class="dash-hero__left">
            <div class="dash-hero__greeting">
                Good <?= (int)date('H') < 12 ? 'morning' : ((int)date('H') < 17 ? 'afternoon' : 'evening') ?>
            </div>
            <div class="dash-hero__name">
                <?= e(explode(' ', $student_name)[0]) ?> 👋
            </div>
            <div class="dash-hero__code"><?= e($sr_code) ?></div>
        </div>

        <div class="dash-hero__stats">

            <div class="hero-stat">
                <div class="hero-stat__value"><?= $total_waiting ?></div>
                <div class="hero-stat__label">In Queue Today</div>
            </div>

            <div class="hero-stat-divider"></div>

            <div class="hero-stat">
                <div class="hero-stat__value">
                    <?= $est_wait_mins !== null ? $est_wait_mins . '<span class="hero-stat__unit">min</span>' : '&mdash;' ?>
                </div>
                <div class="hero-stat__label">Est. Wait</div>
            </div>

            <div class="hero-stat-divider"></div>

            <div class="hero-stat">
                <div class="hero-stat__value"><?= $open_offices ?></div>
                <div class="hero-stat__label">Offices Open</div>
            </div>

        </div>

    </section>

    <!-- ── ACTIVE TICKET ─────────────────────────────── -->
    <?php if ($active_ticket): ?>
    <section class="active-ticket-section">
        <h2 class="section-title">Your Current Queue</h2>

        <div class="active-ticket-card"
             id="active-ticket-widget"
             data-ticket-id="<?= (int)$active_ticket['id'] ?>">

            <div class="active-ticket-card__header">
                <div class="active-ticket-card__number">
                    #<?= e($active_ticket['queue_number']) ?>
                </div>
                <span class="ticket-status-badge ticket-status-badge--<?= e($active_ticket['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $active_ticket['status'])) ?>
                </span>
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
                <a class="btn btn--outline btn--sm"
                   href="/student/queue-status.php?ticket_id=<?= (int)$active_ticket['id'] ?>">
                    Track Queue
                </a>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <!-- ── OFFICES ───────────────────────────────────── -->
    <section class="offices-section">
        <h2 class="section-title">Available Offices</h2>

        <div class="offices-grid">
            <?php foreach ($offices as $office): ?>
            <div class="office-card">
                <div class="office-card__name"><?= e($office['name']) ?></div>
                <div class="office-card__hours">
                    <?= $office['start_time'] ? date('h:i A', strtotime($office['start_time'])) : '08:00 AM' ?>
                    &ndash;
                    <?= $office['end_time'] ? date('h:i A', strtotime($office['end_time'])) : '05:00 PM' ?>
                </div>
                <div class="office-card__actions">
                    <a href="/student/queue.php?office=<?= e($office['slug']) ?>"
                       class="btn btn--outline btn--xs">
                        Join Queue
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/dashboard.js"></script>
</body>
</html>