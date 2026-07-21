<?php
// admin/queue/office-dashboard.php — Office Admin Queue Dashboard
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id        = $_SESSION['office_id'] ?? null;
$target_office_id = $office_id;

// Office admin without an assigned office
if (!$target_office_id) {
    redirect('/auth/logout.php');
}

// Fetch office info
$stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ?");
$stmt->execute([$target_office_id]);
$office = $stmt->fetch();
if (!$office) redirect('/auth/logout.php');

// ── Stats for THIS office, today ──────────────────────────────────────────────
$oid        = $target_office_id;
$today_stats = $pdo->prepare("
    SELECT
        COUNT(*)                                                    AS total,
        SUM(status = 'waiting')                                     AS waiting,
        SUM(status IN ('called','in_progress'))                     AS serving,
        SUM(status = 'completed')                                        AS completed,
        SUM(status = 'cancelled')                                   AS cancelled,
        SUM(type = 'priority' OR priority = 1)                     AS priority_count,
        SUM(type = 'appointment')                                   AS appointments,
        AVG(CASE WHEN done_at IS NOT NULL AND called_at IS NOT NULL
                 THEN TIMESTAMPDIFF(MINUTE, called_at, done_at) END) AS avg_service_min
    FROM queue_tickets
    WHERE office_id = ? AND DATE(joined_at) = CURDATE()
");
$today_stats->execute([$oid]);
$ts = $today_stats->fetch();

/* ───────── Dashboard Chart Data ───────── */

// Queue Status
$queueStatus = [
     (int)$ts['completed'], (int)$ts['cancelled']
];

// Queue Types
$typeStmt = $pdo->prepare("
SELECT SUM(type='walkin') walkin, SUM(type='appointment') appointment
FROM queue_tickets WHERE office_id=? AND DATE(joined_at)=CURDATE()
");

$typeStmt->execute([$oid]);
$type = $typeStmt->fetch();

$queueTypes = [
    (int)$type['walkin'],
    (int)$type['appointment']
];

// Hourly Transactions
$hourStmt = $pdo->prepare("
SELECT HOUR(joined_at) hr, COUNT(*) total
FROM queue_tickets WHERE office_id=? AND DATE(joined_at)=CURDATE() GROUP BY HOUR(joined_at) ORDER BY hr
");

$hourStmt->execute([$oid]);

$hours = [];
$hourTotals = [];

while($r = $hourStmt->fetch()){
    $hours[] = sprintf('%02d:00',$r['hr']);
    $hourTotals[] = (int)$r['total'];
}

// Window Performance
$windowStmt = $pdo->prepare("
SELECT w.name, COUNT(q.id) total
FROM windows w LEFT JOIN queue_tickets q ON q.window_id=w.id AND q.status='completed'
AND DATE(q.done_at)=CURDATE() WHERE w.office_id=? GROUP BY w.id
");

$windowStmt->execute([$oid]);

$windowNames=[];
$windowTotals=[];

while($r=$windowStmt->fetch()){
    $windowNames[]=$r['name'];
    $windowTotals[]=(int)$r['total'];
}

// Documents
$docStmt=$pdo->prepare("
SELECT d.name, COUNT(td.document_id) total
FROM queue_ticket_document td JOIN documents d ON d.id=td.document_id
JOIN queue_tickets qt ON qt.id=td.ticket_id WHERE qt.office_id=?
AND DATE(qt.joined_at)=CURDATE() GROUP BY d.id ORDER BY total DESC LIMIT 10
");

$docStmt->execute([$oid]);

$docNames=[];
$docTotals=[];

while($r=$docStmt->fetch()){
    $docNames[]=$r['name'];
    $docTotals[]=(int)$r['total'];
}

// ── Windows for this office ───────────────────────────────────────────────
$win_stmt = $pdo->prepare("
    SELECT
        w.*,

        (
            SELECT name
            FROM staff
            WHERE window_id = w.id
            LIMIT 1
        ) AS staff_name,

        (
            SELECT COUNT(*)
            FROM queue_tickets qt
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
              AND DATE(qt.joined_at) = CURDATE()
        ) AS active_tickets,

        (
            SELECT s.first_name
            FROM queue_tickets qt
            INNER JOIN students s
                ON s.id = qt.student_id
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
            ORDER BY qt.called_at DESC
            LIMIT 1
        ) AS current_student_fname,

        (
            SELECT qt.queue_number
            FROM queue_tickets qt
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
            ORDER BY qt.called_at DESC
            LIMIT 1
        ) AS current_ticket_num

    FROM windows w
    WHERE w.office_id = ?
    ORDER BY w.name ASC
");

    $win_stmt->execute([$oid]);
    $windows = $win_stmt->fetchAll();

$pageTitle = "Dashboard — " . $office['name'];
include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/admin-bootstrap-theme.css">
<link rel="stylesheet" href="/assets/css/office-dashboard.css">
<link rel="stylesheet" href="/assets/css/header.css">


<div class="app-shell">

    <!-- ── Sidebar nav (centralized) ──────────────────────────────────────── -->
    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

    <div class="od-wrap">

    <div class="sr-only" role="status" aria-live="polite" id="dashboard-status-region"
         style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"></div>

    <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1><?= htmlspecialchars($office['name']) ?></h1>
            <p>Queue Dashboard &nbsp;·&nbsp; <?= date('l, F j, Y') ?></p>
        </div>
    </div>

    <!-- ── Today's stats ────────────────────────────────────────────────────── -->
    <div class="stats-row">
        <div class="stat-box s-red">
            <span class="stat-box__lbl">Total Today</span>
            <span class="stat-box__val"><?= (int)$ts['total'] ?></span>
        </div>
        <div class="stat-box s-amber">
            <span class="stat-box__lbl">Waiting</span>
            <span class="stat-box__val"><?= (int)$ts['waiting'] ?></span>
        </div>
        <div class="stat-box s-teal">
            <span class="stat-box__lbl">Serving</span>
            <span class="stat-box__val"><?= (int)$ts['serving'] ?></span>
        </div>
        <div class="stat-box s-green">
            <span class="stat-box__lbl">Completed</span>
            <span class="stat-box__val"><?= (int)$ts['completed'] ?></span>
        </div>
        <div class="stat-box s-blue">
            <span class="stat-box__lbl">Appointments</span>
            <span class="stat-box__val"><?= (int)$ts['appointments'] ?></span>
        </div>
        <div class="stat-box s-violet">
            <span class="stat-box__lbl">Priority</span>
            <span class="stat-box__val"><?= (int)$ts['priority_count'] ?></span>
        </div>
    </div>

    <!-- ── Main grid ────────────────────────────────────────────────────────── -->
    <div class="od-grid">

        <!-- Left: windows ──────────────────────────────────────────────────── -->
        <aside class="windows-col" aria-label="Service windows">
            <div class="sec-label">Service Windows</div>

            <?php if (empty($windows)): ?>
                <p style="color:var(--dim);font-size:.85rem;padding:.5rem 0;">
                    No windows configured.
                </p>
            <?php endif; ?>

            <?php foreach ($windows as $w): ?>
            <article class="window-card is-<?= htmlspecialchars($w['status']) ?>">

                <div class="window-card__top">
                    <span class="window-card__name"><?= htmlspecialchars($w['name']) ?></span>
                    <span class="status-dot <?= htmlspecialchars($w['status']) ?>"
                          title="<?= ucfirst($w['status']) ?>"
                          aria-label="Status: <?= ucfirst($w['status']) ?>"></span>
                </div>

                <div class="window-card__meta">
                    Speed: <?= ucfirst($w['speed']) ?>
                    &nbsp;·&nbsp;
                    Status: <strong style="color: <?= $w['status'] === 'open' ? 'var(--green)' : 'var(--muted)' ?>">
                        <?= ucfirst($w['status']) ?>
                    </strong>

                       <?php if (!empty($w['staff_name'])): ?>
                    <br>
                    Staff:
                    <strong><?= htmlspecialchars($w['staff_name']) ?></strong>
                    <?php else: ?>
                        <br>
                        <span style="color:#dc2626;">
                            No Staff Assigned
                        </span>
                    <?php endif; ?>
                </div>

             

                <div class="window-card__serving">
                    <?php if ($w['current_ticket_num']): ?>
                        <div class="ticket-num"><?= htmlspecialchars($w['current_ticket_num']) ?></div>
                        <div class="student-name"><?= htmlspecialchars($w['current_student_fname'] ?? 'Student') ?></div>
                    <?php else: ?>
                        <span class="empty-slot">
                            <?= $w['status'] === 'open' ? 'Idle — ready for next' : 'Window closed' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <button
                    class="btn btn-sm <?= $w['status'] === 'open' ? 'btn-outline-danger' : 'btn-outline-success' ?> window-card__toggle btn-toggle-counter"
                    data-id="<?= (int)$w['id'] ?>"
                    data-status="<?= htmlspecialchars($w['status']) ?>"
                    title="<?= $w['status'] === 'open' ? 'Stop serving on this window' : 'Start serving on this window' ?>"
                    aria-label="<?= $w['status'] === 'open' ? 'Close' : 'Open' ?> <?= htmlspecialchars($w['name']) ?>">
                    <?= $w['status'] === 'open' ? 'Close Window' : 'Open Window' ?>
                </button>

            </article>
            <?php endforeach; ?>
        </aside>

        <!-- Right: queue panels (JS-driven) ────────────────────────────────── -->
        <div class="queue-col">

            <div class="dashboard-charts">

                <!-- Hourly trend leads — it's the most useful at-a-glance signal -->
                <section class="chart-card chart-card--wide">
                    <div class="chart-card__head">
                        <h3>Transactions Per Hour</h3>
                        <span>Today's Activity</span>
                    </div>

                    <canvas id="hourlyChart"></canvas>
                </section>

                <!-- Queue Status -->
                <section class="chart-card">
                    <div class="chart-card__head">
                        <h3>Queue Status</h3>
                        <span>Today's Queue Distribution</span>
                    </div>

                    <canvas id="queueStatusChart"></canvas>
                </section>

                <!-- Queue Type -->
                <section class="chart-card">
                    <div class="chart-card__head">
                        <h3>Queue Types</h3>
                        <span>Walk-in vs Appointment</span>
                    </div>

                    <canvas id="queueTypeChart"></canvas>
                </section>

                <!-- Windows -->
                <section class="chart-card chart-card--wide">
                    <div class="chart-card__head">
                        <h3>Window Performance</h3>
                        <span>Completed Transactions</span>
                    </div>

                    <canvas id="windowChart"></canvas>
                </section>

                <!-- Documents -->
                <section class="chart-card chart-card--wide">
                    <div class="chart-card__head">
                        <h3>Most Requested Documents</h3>
                        <span>Today's Requests</span>
                    </div>

                    <canvas id="documentsChart"></canvas>
                </section>

            </div>
        </div><!-- /.queue-col -->

    </div><!-- /.od-grid -->

    <!-- Kept INSIDE #od-wrap on purpose: spa-nav.js only swaps/re-runs
         markup and scripts that live inside #od-wrap. If these tags sit
         outside it (as they used to), navigating back to this page via
         AJAX never re-executes them, so the charts stay blank until a
         full manual reload. -->
    <script src="/assets/js/vendor/chart.umd.min.js"></script>

    <script>
    const CURRENT_OFFICE_ID = <?= (int)$target_office_id ?>;
    const REFRESH_MS = 15000;
    const queueStatus = <?= json_encode($queueStatus) ?>;
    const queueTypes = <?= json_encode($queueTypes) ?>;
    const hourlyLabels = <?= json_encode($hours) ?>;
    const hourlyData = <?= json_encode($hourTotals) ?>;
    const windowLabels = <?= json_encode($windowNames) ?>;
    const windowData = <?= json_encode($windowTotals) ?>;
    const documentLabels = <?= json_encode($docNames) ?>;
    const documentData = <?= json_encode($docTotals) ?>;
    </script>

    <script src="/assets/js/office-dashboard.js"></script>
    <script src="/assets/js/smart-assign.js"></script>


    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>