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

// ── Date range slicer (default: Today) ─────────────────────────────────────
// Accepted ?range= values: today | yesterday | 7d | 30d | month | custom
$allowed_ranges = ['today', 'yesterday', '7d', '30d', 'month', 'custom'];
$range = $_GET['range'] ?? 'today';
if (!in_array($range, $allowed_ranges, true)) $range = 'today';

$today = new DateTime('today');

switch ($range) {
    case 'yesterday':
        $dateFrom = $dateTo = (clone $today)->modify('-1 day')->format('Y-m-d');
        break;
    case '7d':
        $dateFrom = (clone $today)->modify('-6 day')->format('Y-m-d');
        $dateTo   = $today->format('Y-m-d');
        break;
    case '30d':
        $dateFrom = (clone $today)->modify('-29 day')->format('Y-m-d');
        $dateTo   = $today->format('Y-m-d');
        break;
    case 'month':
        $dateFrom = (clone $today)->modify('first day of this month')->format('Y-m-d');
        $dateTo   = $today->format('Y-m-d');
        break;
    case 'custom':
        $from_in = $_GET['from'] ?? '';
        $to_in   = $_GET['to'] ?? '';
        $validDate = fn($d) => (bool)DateTime::createFromFormat('Y-m-d', $d);
        if ($validDate($from_in) && $validDate($to_in)) {
            $dateFrom = $from_in;
            $dateTo   = $to_in;
            // Keep from <= to
            if ($dateFrom > $dateTo) { [$dateFrom, $dateTo] = [$dateTo, $dateFrom]; }
        } else {
            // Fall back to today if custom dates are missing/invalid
            $range    = 'today';
            $dateFrom = $dateTo = $today->format('Y-m-d');
        }
        break;
    case 'today':
    default:
        $dateFrom = $dateTo = $today->format('Y-m-d');
        break;
}

$isToday = ($range === 'today');

// ── Stats for THIS office, for the selected date range ─────────────────────
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
    WHERE office_id = ? AND DATE(joined_at) BETWEEN ? AND ?
");
$today_stats->execute([$oid, $dateFrom, $dateTo]);
$ts = $today_stats->fetch();

// Average Wait Time (in minutes)
$avgWaitStmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, joined_at, called_at)) AS avg_wait_min
    FROM queue_tickets
    WHERE office_id = ? AND DATE(joined_at) BETWEEN ? AND ? AND called_at IS NOT NULL
");
$avgWaitStmt->execute([$oid, $dateFrom, $dateTo]);
$avgWaitMin = $avgWaitStmt->fetchColumn();

/* ───────── Dashboard Chart Data ───────── */

// Queue "All" Statuses
$queueAll = [
    (int)$ts['waiting'],
    (int)$ts['serving'],
    (int)$ts['completed'],
    (int)$ts['cancelled'],
];

// Queue Status
$queueStatus = [
     (int)$ts['completed'], (int)$ts['cancelled']
];

// Queue Types
$typeStmt = $pdo->prepare("
SELECT SUM(type='walkin') walkin, SUM(type='appointment') appointment
FROM queue_tickets WHERE office_id=? AND DATE(joined_at) BETWEEN ? AND ?
");

$typeStmt->execute([$oid, $dateFrom, $dateTo]);
$type = $typeStmt->fetch();

$queueTypes = [
    (int)$type['walkin'],
    (int)$type['appointment']
];

// Trend chart: hourly breakdown for a single day, daily breakdown across a range
$isSingleDay = ($dateFrom === $dateTo);

if ($isSingleDay) {
    $hourStmt = $pdo->prepare("
    SELECT HOUR(joined_at) hr, COUNT(*) total
    FROM queue_tickets WHERE office_id=? AND DATE(joined_at) = ? GROUP BY HOUR(joined_at) ORDER BY hr
    ");
    $hourStmt->execute([$oid, $dateFrom]);

    $hours = [];
    $hourTotals = [];

    while($r = $hourStmt->fetch()){
        $hours[] = sprintf('%02d:00',$r['hr']);
        $hourTotals[] = (int)$r['total'];
    }
    $trendLabel = "Today's Activity";
} else {
    $hourStmt = $pdo->prepare("
    SELECT DATE(joined_at) d, COUNT(*) total
    FROM queue_tickets WHERE office_id=? AND DATE(joined_at) BETWEEN ? AND ?
    GROUP BY DATE(joined_at) ORDER BY d
    ");
    $hourStmt->execute([$oid, $dateFrom, $dateTo]);

    $hours = [];
    $hourTotals = [];

    while($r = $hourStmt->fetch()){
        $hours[] = date('M j', strtotime($r['d']));
        $hourTotals[] = (int)$r['total'];
    }
    $trendLabel = date('M j', strtotime($dateFrom)) . ' – ' . date('M j', strtotime($dateTo));
}

// Window Performance
$windowStmt = $pdo->prepare("
SELECT w.name, COUNT(q.id) total
FROM windows w LEFT JOIN queue_tickets q ON q.window_id=w.id AND q.status='completed'
AND DATE(q.done_at) BETWEEN ? AND ? WHERE w.office_id=? GROUP BY w.id
");

$windowStmt->execute([$dateFrom, $dateTo, $oid]);

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
AND DATE(qt.joined_at) BETWEEN ? AND ? GROUP BY d.id ORDER BY total DESC LIMIT 10
");

$docStmt->execute([$oid, $dateFrom, $dateTo]);

$docNames=[];
$docTotals=[];

while($r=$docStmt->fetch()){
    $docNames[]=$r['name'];
    $docTotals[]=(int)$r['total'];
}

// Feedback Ratings
$feedbackStmt = $pdo->prepare("
    SELECT rating, COUNT(id) as total
    FROM feedbacks
    WHERE ticket_id IN (SELECT id FROM queue_tickets WHERE office_id = ? AND DATE(joined_at) BETWEEN ? AND ?)
    GROUP BY rating ORDER BY rating ASC
");
$feedbackStmt->execute([$oid, $dateFrom, $dateTo]);
$feedbackData = array_fill(1, 5, 0);
while ($r = $feedbackStmt->fetch()) {
    $feedbackData[(int)$r['rating']] = (int)$r['total'];
}
$feedbackRatings = array_values($feedbackData);

// Tickets by College
$collegeStmt = $pdo->prepare("
    SELECT c.abbreviation, COUNT(qt.id) as total
    FROM queue_tickets qt
    JOIN students s ON s.id = qt.student_id
    JOIN colleges c ON c.id = s.college_id
    WHERE qt.office_id = ? AND DATE(qt.joined_at) BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total DESC
");
$collegeStmt->execute([$oid, $dateFrom, $dateTo]);
$collegeLabels = [];
$collegeTotals = [];
while($r = $collegeStmt->fetch()) {
    $collegeLabels[] = $r['abbreviation'];
    $collegeTotals[] = (int)$r['total'];
}

// ── AJAX Response ──────────────────────────────────────────────────────────
// If this is an AJAX request (from the date slicer), send back only the
// data needed to re-render the dashboard, then exit.
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$ts['total'],
            'waiting' => (int)$ts['waiting'],
            'serving' => (int)$ts['serving'],
            'completed' => (int)$ts['completed'],
            'appointments' => (int)$ts['appointments'],
            'priority_count' => (int)$ts['priority_count'],
            'avg_service_min' => $ts['avg_service_min'] !== null ? round($ts['avg_service_min']) : null,
        ],
        'queueAll' => $queueAll,
        'queueStatus' => $queueStatus,
        'queueTypes' => $queueTypes,
        'hourlyLabels' => $hours,
        'hourlyData' => $hourTotals,
        'windowLabels' => $windowNames,
        'windowData' => $windowTotals,
        'documentLabels' => $docNames,
        'documentData' => $docTotals,
        'feedbackRatings' => $feedbackRatings,
        'collegeLabels' => $collegeLabels,
        'collegeData' => $collegeTotals,
        'isToday' => $isToday,
        'trendLabel' => $trendLabel,
        'pageTitle' => "Dashboard — " . $office['name'],
        'headerDate' => $isSingleDay
            ? date('l, F j, Y', strtotime($dateFrom))
            : date('M j', strtotime($dateFrom)) . ' – ' . date('M j, Y', strtotime($dateTo)),
    ]);
    exit;
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

$windows_total = count($windows);
$windows_open  = count(array_filter($windows, fn($w) => $w['status'] === 'open'));

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
            <span class="od-topbar__eyebrow">Office Administration</span>
            <h1><?= htmlspecialchars($office['name']) ?></h1>
            <p id="od-header-date">Queue Dashboard &nbsp;·&nbsp;
                <?= $isSingleDay
                    ? date('l, F j, Y', strtotime($dateFrom))
                    : date('M j', strtotime($dateFrom)) . ' – ' . date('M j, Y', strtotime($dateTo)) ?>
            </p>
        </div>
        <div class="od-topbar__actions">
            <form id="dashboard-slicer" class="dashboard-slicer" method="get">
                <div class="dashboard-slicer__select-wrap">
                    <select name="range" id="slicer-range" class="dashboard-slicer__select">
                        <option value="today"     <?= $range === 'today'     ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $range === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="7d"        <?= $range === '7d'        ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30d"       <?= $range === '30d'       ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="month"     <?= $range === 'month'     ? 'selected' : '' ?>>This Month</option>
                        <option value="custom"    <?= $range === 'custom'    ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                    <svg class="dashboard-slicer__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="dashboard-slicer__custom<?= $range === 'custom' ? ' is-visible' : '' ?>" id="slicer-custom">
                    <input type="date" name="from" id="slicer-from" value="<?= htmlspecialchars($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
                    <span class="dashboard-slicer__sep">to</span>
                    <input type="date" name="to" id="slicer-to" value="<?= htmlspecialchars($dateTo) ?>" max="<?= date('Y-m-d') ?>">
                    <button type="submit" class="dashboard-slicer__apply">Apply</button>
                </div>
                <?php if ($isToday): ?>
                    <span class="dashboard-slicer__live" title="Auto-refreshing every 15s">
                        <span class="dashboard-slicer__live-dot"></span> Live
                    </span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ── Today's stats ────────────────────────────────────────────────────── -->
    <div class="stats-row">
        <div class="stat-box s-red">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M9 3.13a4 4 0 0 0 0 7.75"/><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl"><?= $isToday ? 'Total Today' : 'Total' ?></span>
                <span class="stat-box__val" data-stat="total"><?= (int)$ts['total'] ?></span>
            </div>
        </div>
        <div class="stat-box s-amber">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Waiting</span>
                <span class="stat-box__val" data-stat="waiting"><?= (int)$ts['waiting'] ?></span>
            </div>
        </div>
        <div class="stat-box s-teal">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 21V8a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v13"/><path d="M14 21V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v17"/><path d="M2 21h20"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Serving</span>
                <span class="stat-box__val" data-stat="serving"><?= (int)$ts['serving'] ?></span>
            </div>
        </div>
        <div class="stat-box s-green">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Completed</span>
                <span class="stat-box__val" data-stat="completed"><?= (int)$ts['completed'] ?></span>
            </div>
        </div>
        <div class="stat-box s-blue">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Appointments</span>
                <span class="stat-box__val" data-stat="appointments"><?= (int)$ts['appointments'] ?></span>
            </div>
        </div>
        <div class="stat-box s-violet">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2l2.4 7.2H22l-6 4.6 2.3 7.2-6.3-4.5-6.3 4.5 2.3-7.2-6-4.6h7.6z"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Priority</span>
                <span class="stat-box__val" data-stat="priority_count"><?= (int)$ts['priority_count'] ?></span>
            </div>
        </div>
        <div class="stat-box s-teal">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 21V8a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v13"/><path d="M14 21V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v17"/><path d="M2 21h20"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Windows Open</span>
                <span class="stat-box__val"><?= $windows_open ?>/<?= $windows_total ?></span>
            </div>
        </div>
        <div class="stat-box s-slate">
            <div class="stat-box__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 7 12 12 15.5 14"/>
                </svg>
            </div>
            <div class="stat-box__body">
                <span class="stat-box__lbl">Avg. Service Time</span>
                <span class="stat-box__val" data-stat="avg_service_min"><?= $ts['avg_service_min'] !== null ? round($ts['avg_service_min']) . '<small>m</small>' : 'N/A' ?></span>
            </div>
        </div>
    </div>

    <!-- ── Charts (JS-driven) ──────────────────────────────────────────────── -->
    <div class="queue-col">

            <div class="dashboard-charts">

                <!-- Hourly trend leads — it's the most useful at-a-glance signal -->
                <section class="chart-card chart-card-top">
                    <div class="chart-card__head">
                        <div class="chart-card__head-left">
                            <div class="chart-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.1-2.8-2.8L7 14.1"/>
                                </svg>
                            </div>
                            <div>
                                <h3 data-title="hourly"><?= $isSingleDay ? 'Transactions Per Hour' : 'Transactions Per Day' ?></h3>
                                <span data-subtitle="hourly"><?= htmlspecialchars($trendLabel) ?></span>
                            </div>
                        </div>
                    </div>

                    <div id="hourlyChart"></div>
                </section>

                <!-- Documents -->
                <section class="chart-card chart-card-top">
                    <div class="chart-card__head">
                        <div class="chart-card__head-left">
                            <div class="chart-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div>
                                <h3 data-title="documents">Most Requested Documents</h3>
                                <span data-subtitle="documents"><?= $isToday ? "Today's Requests" : 'Document Requests' ?></span>
                            </div>
                        </div>
                    </div>

                    <div id="documentsChart"></div>
                </section>

                <!-- Queue Overview — Status + Type combined into one card -->
                <section class="chart-card">
                    <div class="chart-card__head">
                        <div class="chart-card__head-left">
                            <div class="chart-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>
                                </svg>
                            </div>
                            <div>
                                <h3>Queue Overview</h3>
                                <span id="queueOverviewSubtitle"><?= $isToday ? "Today's Queue Distribution" : 'Queue Distribution' ?></span>
                            </div>
                        </div>
                        <div class="chart-toggle" id="queueOverviewToggle" role="tablist" aria-label="Queue overview breakdown">
                            <button type="button" class="chart-toggle__btn is-active" data-view="all" role="tab" aria-selected="true">All</button>
                            <button type="button" class="chart-toggle__btn" data-view="status" role="tab" aria-selected="false">Status</button>
                            <button type="button" class="chart-toggle__btn" data-view="type" role="tab" aria-selected="false">Type</button>
                        </div>
                    </div>

                    <div id="queueOverviewChart"></div>
                </section>


                <!-- Windows -->
                <section class="chart-card">
                    <div class="chart-card__head">
                        <div class="chart-card__head-left">
                            <div class="chart-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 21V8a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v13"/><path d="M14 21V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v17"/><path d="M2 21h20"/>
                                </svg>
                            </div>
                            <div>
                                <h3>Window Performance</h3>
                                <span>Completed Transactions</span>
                            </div>
                        </div>
                    </div>

                    <div id="windowChart"></div>
                </section>

                <!-- Feedback Ratings -->
                <section class="chart-card">
                        <div class="chart-card__head">
                            <div class="chart-card__head-left">
                                <div class="chart-card__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2l2.4 7.2H22l-6 4.6 2.3 7.2-6.3-4.5-6.3 4.5 2.3-7.2-6-4.6h7.6z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3>Feedback Ratings</h3>
                                    <span>Student satisfaction scores</span>
                                </div>
                            </div>
                        </div>
                        <div id="feedbackChart"></div>
                </section>

                <!-- Tickets by College -->
                <section class="chart-card">
                        <div class="chart-card__head">
                            <div class="chart-card__head-left">
                                <div class="chart-card__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 21V8a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v13"/><path d="M14 21V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v17"/><path d="M2 21h20"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3>Tickets by College</h3>
                                    <span>Distribution across colleges</span>
                                </div>
                            </div>
                        </div>
                        <div id="collegeChart"></div>
                </section>

            </div>
    </div><!-- /.queue-col -->

    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

    <script>
    const CURRENT_OFFICE_ID = <?= (int)$target_office_id ?>;
    const REFRESH_MS = 15000;
    const CURRENT_RANGE = <?= json_encode($range) ?>;
    const CURRENT_FROM  = <?= json_encode($dateFrom) ?>;
    const CURRENT_TO    = <?= json_encode($dateTo) ?>;
    const IS_TODAY      = <?= $isToday ? 'true' : 'false' ?>;
    const queueAll = <?= json_encode($queueAll) ?>;
    const queueStatus = <?= json_encode($queueStatus) ?>;
    const queueTypes = <?= json_encode($queueTypes) ?>;
    const hourlyLabels = <?= json_encode($hours) ?>;
    const hourlyData = <?= json_encode($hourTotals) ?>;
    const windowLabels = <?= json_encode($windowNames) ?>;
    const windowData = <?= json_encode($windowTotals) ?>;
    const documentLabels = <?= json_encode($docNames) ?>;
    const documentData = <?= json_encode($docTotals) ?>;
    const feedbackRatings = <?= json_encode($feedbackRatings) ?>;
    const collegeLabels = <?= json_encode($collegeLabels) ?>;
    const collegeData = <?= json_encode($collegeTotals) ?>;
    </script>

    <script src="/assets/js/office-dashboard.js"></script>
    <script src="/assets/js/smart-assign.js"></script>


    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>