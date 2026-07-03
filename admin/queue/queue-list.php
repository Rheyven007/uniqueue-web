<?php
// admin/queue/queue-list.php — Office Queue List (Walk-in / Appointment / All)
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id        = $_SESSION['office_id'] ?? null;
$target_office_id = $office_id;

if (!$target_office_id) {
    redirect('/auth/logout.php');
}

// Fetch office info
$stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ?");
$stmt->execute([$target_office_id]);
$office = $stmt->fetch();
if (!$office) redirect('/auth/logout.php');

$oid = $target_office_id;

// Active tab: all | walkin | appointment
$tab = $_GET['type'] ?? 'all';
if (!in_array($tab, ['all', 'walkin', 'appointment'], true)) {
    $tab = 'all';
}

// Selected counter/window filter: 'all', 'unassigned', or a window id
$window_filter = $_GET['window'] ?? 'all';

// Windows for this office (for the filter dropdown + grouping)
$windows_stmt = $pdo->prepare("SELECT id, name FROM windows WHERE office_id = ? ORDER BY name ASC");
$windows_stmt->execute([$oid]);
$windows = $windows_stmt->fetchAll();

// ── Build query based on tab ───────────────────────────────────────────────
$where  = "qt.office_id = ? AND DATE(qt.joined_at) = CURDATE()";
$params = [$oid];

if ($tab === 'walkin') {
    $where .= " AND qt.type = 'walkin'";
} elseif ($tab === 'appointment') {
    $where .= " AND qt.type = 'appointment'";
}

if ($window_filter === 'unassigned') {
    $where .= " AND qt.window_id IS NULL";
} elseif ($window_filter !== 'all' && ctype_digit((string)$window_filter)) {
    $where .= " AND qt.window_id = ?";
    $params[] = (int)$window_filter;
}

$list_stmt = $pdo->prepare("
    SELECT
        qt.id,
        qt.queue_number,
        qt.type,
        qt.priority,
        qt.status,
        qt.joined_at,
        qt.called_at,
        qt.done_at,
        qt.window_id,
        s.first_name,
        s.last_name,
        s.sr_code,
        w.name AS window_name
    FROM queue_tickets qt
    LEFT JOIN students s ON qt.student_id = s.id
    LEFT JOIN windows  w ON qt.window_id  = w.id
    WHERE {$where}
    ORDER BY (w.name IS NULL), w.name ASC, qt.joined_at DESC
");
$list_stmt->execute($params);
$tickets = $list_stmt->fetchAll();

// Group tickets by window for sectioned display
$grouped = [];
foreach ($tickets as $t) {
    $key = $t['window_id'] ?? 'unassigned';
    $grouped[$key]['label'] = $t['window_name'] ?? 'Unassigned';
    $grouped[$key]['tickets'][] = $t;
}

// ── Counts for tab badges ────────────────────────────────────────────────────
$count_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(type = 'walkin')       AS walkin_count,
        SUM(type = 'appointment')  AS appointment_count
    FROM queue_tickets
    WHERE office_id = ? AND DATE(joined_at) = CURDATE()
");
$count_stmt->execute([$oid]);
$counts = $count_stmt->fetch();

$status_classes = [
    'waiting'     => 'badge-status-waiting',
    'called'      => 'badge-status-called',
    'in_progress' => 'badge-status-called',
    'done'        => 'badge-status-done',
    'cancelled'   => 'badge-status-cancelled',
];

$pageTitle = "Queue List — " . $office['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="od-wrap">

    <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1><?= htmlspecialchars($office['name']) ?></h1>
            <p>Queue List &nbsp;·&nbsp; <?= date('l, F j, Y') ?></p>
        </div>

        <div class="od-actions">
            <a href="/admin/queue/office-dashboard.php" class="btn btn-ghost">&larr; Back to Dashboard</a>
        </div>
    </div>

    <!-- ── Tabs ─────────────────────────────────────────────────────────────── -->
    <div class="ql-tabs" role="tablist" aria-label="Queue type">
        <a href="?type=all" role="tab" aria-selected="<?= $tab === 'all' ? 'true' : 'false' ?>"
           class="ql-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
            All
            <span class="count-badge teal"><?= (int)$counts['total'] ?></span>
        </a>
        <a href="?type=walkin" role="tab" aria-selected="<?= $tab === 'walkin' ? 'true' : 'false' ?>"
           class="ql-tab <?= $tab === 'walkin' ? 'is-active' : '' ?>">
            Walk-in
            <span class="count-badge amber"><?= (int)$counts['walkin_count'] ?></span>
        </a>
        <a href="?type=appointment" role="tab" aria-selected="<?= $tab === 'appointment' ? 'true' : 'false' ?>"
           class="ql-tab <?= $tab === 'appointment' ? 'is-active' : '' ?>">
            Appointment
            <span class="count-badge violet"><?= (int)$counts['appointment_count'] ?></span>
        </a>
    </div>

    <!-- ── Counter filter ───────────────────────────────────────────────────── -->
    <form method="get" class="ql-filter" id="counter-filter-form">
        <input type="hidden" name="type" value="<?= htmlspecialchars($tab) ?>">
        <label for="window-select" class="ql-filter__label">Counter:</label>
        <select name="window" id="window-select" class="form-control" onchange="this.form.submit()">
            <option value="all" <?= $window_filter === 'all' ? 'selected' : '' ?>>All Counters</option>
            <?php foreach ($windows as $w): ?>
                <option value="<?= (int)$w['id'] ?>" <?= (string)$window_filter === (string)$w['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($w['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- ── Queue table(s), grouped by counter ──────────────────────────────── -->
    <?php if (empty($tickets)): ?>
        <section class="queue-section" aria-label="Queue tickets">
            <div class="empty-state">No tickets found for this view.</div>
        </section>
    <?php else: ?>
        <?php foreach ($grouped as $group): ?>
        <section class="queue-section ql-counter-section" aria-label="Tickets for <?= htmlspecialchars($group['label']) ?>">
            <div class="queue-section__head">
                <h2>
                    <?= htmlspecialchars($group['label']) ?>
                    <span class="count-badge teal"><?= count($group['tickets']) ?></span>
                </h2>
            </div>
            <div class="ql-table-wrap">
                <table class="ql-table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Student</th>
                            <th>SR Code</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Called</th>
                            <th>Done</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['tickets'] as $t): ?>
                        <tr class="<?= $t['priority'] ? 'is-priority' : '' ?>">
                            <td class="ql-num"><?= htmlspecialchars($t['queue_number']) ?></td>
                            <td>
                                <?= htmlspecialchars(trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))) ?: '—' ?>
                                <?php if ($t['priority']): ?>
                                    <span class="badge badge-priority">Priority</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['sr_code'] ?? '—') ?></td>
                            <td><span class="badge badge-type"><?= htmlspecialchars($t['type']) ?></span></td>
                            <td>
                                <span class="badge <?= $status_classes[$t['status']] ?? 'badge-window' ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $t['status'])) ?>
                                </span>
                            </td>
                            <td><?= $t['joined_at'] ? date('h:i A', strtotime($t['joined_at'])) : '—' ?></td>
                            <td><?= $t['called_at'] ? date('h:i A', strtotime($t['called_at'])) : '—' ?></td>
                            <td><?= $t['done_at']   ? date('h:i A', strtotime($t['done_at']))   : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /.od-wrap -->

<link rel="stylesheet" href="/assets/css/office-dashboard.css">
<link rel="stylesheet" href="/assets/css/queue-list.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>