<?php
// admin/queue/queue-list.php — Office Queue List (Walk-in / Appointment / All)
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_office_admin();

$office_id        = $_SESSION['office_id'] ?? null;
$target_office_id = $office_id;

if (!$target_office_id) {
    redirect('/auth/logout.php');
}


// Active tab: all | walkin | appointment
$tab = $_GET['type'] ?? 'all';
if (!in_array($tab, ['all', 'walkin', 'appointment'], true)) {
    $tab = 'all';
}

// Selected counter/window filter: 'all', 'unassigned', or a window id
$window_filter = $_GET['window'] ?? 'all';

$office = null;
$windows = [];
$tickets = [];
$counts = ['total' => 0, 'walkin_count' => 0, 'appointment_count' => 0];
$db_error = null;

try {
    // Fetch office info
    $stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ?");
    $stmt->execute([$target_office_id]);
    $office = $stmt->fetch();
    if (!$office) redirect('/auth/logout.php');

    $oid = $target_office_id;

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
            qt.id, qt.queue_number, qt.type, qt.priority, qt.status,
            qt.joined_at, qt.called_at, qt.done_at, qt.window_id,
            s.first_name, s.last_name, s.sr_code,
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
            SUM(type = 'walkin') AS walkin_count,
            SUM(type = 'appointment') AS appointment_count
        FROM queue_tickets
        WHERE office_id = ? AND DATE(joined_at) = CURDATE()
    ");
    $count_stmt->execute([$oid]);
    $counts = $count_stmt->fetch();

} catch (PDOException $e) {
    // Log error and set a user-friendly message
    error_log("Queue List DB Error: " . $e->getMessage());
    $db_error = "A database error occurred. Please try again later.";
}

$status_classes = [
    'waiting'     => 'badge-status-waiting',
    'called'      => 'badge-status-called',
    'in_progress' => 'badge-status-called',
    'done'        => 'badge-status-done',
    'cancelled'   => 'badge-status-cancelled',
];

$pageTitle = "Queue List — " . ($office['name'] ?? 'Office');
include __DIR__ . '/../../includes/header.php';
?>

<div class="app-shell">

    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

<div class="od-wrap">

    <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1><?= htmlspecialchars($office['name'] ?? 'Queue List') ?></h1>
            <p>Queue List &nbsp;·&nbsp; <?= date('l, F j, Y') ?></p>
        </div>

        <div class="od-actions">
            <button class="btn btn-ghost btn-sm" onclick="window.location.reload()" aria-label="Refresh list">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6"/>
                    <path d="M21.34 15.57a10 10 0 1 1-.4-4.57"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>


    <div class="ql-controls-bar">
        <div class="ql-tabs" role="tablist" aria-label="Queue type">
            <a href="?type=all&window=<?= htmlspecialchars($window_filter) ?>" role="tab" aria-selected="<?= $tab === 'all' ? 'true' : 'false' ?>"
               class="ql-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
                All
                <span class="count-badge green"><?= (int)$counts['total'] ?></span>
            </a>
            <a href="?type=walkin&window=<?= htmlspecialchars($window_filter) ?>" role="tab" aria-selected="<?= $tab === 'walkin' ? 'true' : 'false' ?>"
               class="ql-tab <?= $tab === 'walkin' ? 'is-active' : '' ?>">
                Walk-in
                <span class="count-badge amber"><?= (int)$counts['walkin_count'] ?></span>
            </a>
            <a href="?type=appointment&window=<?= htmlspecialchars($window_filter) ?>" role="tab" aria-selected="<?= $tab === 'appointment' ? 'true' : 'false' ?>"
               class="ql-tab <?= $tab === 'appointment' ? 'is-active' : '' ?>">
                Appointment
                <span class="count-badge violet"><?= (int)$counts['appointment_count'] ?></span>
            </a>
        </div>

        <div class="ql-filter">
            <label for="window-select" class="ql-filter__label">Counter:</label>
            <select name="window" id="window-select" class="form-control" onchange="window.location.href = '?type=<?= e($tab) ?>&window=' + this.value">
                <option value="all" <?= $window_filter === 'all' ? 'selected' : '' ?>>All Counters</option>
                <?php foreach ($windows as $w): ?>
                    <option value="<?= (int)$w['id'] ?>" <?= (string)$window_filter === (string)$w['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($w['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <!-- ── Filter summary ───────────────────────────────────────────────────── -->
    <div class="ql-filter-summary">
        <span class="sr-only" role="status" aria-live="polite">
            Showing <?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?> for the current filters.
        </span>
        <div class="ql-summary-text" aria-hidden="true">
            Showing <strong><?= count($tickets) ?></strong> ticket<?= count($tickets) === 1 ? '' : 's' ?> for:
            <span class="ql-summary-tag">
                Type: <strong><?= e(ucfirst($tab)) ?></strong>
            </span>
            <span class="ql-summary-tag">
                Counter: <strong><?php
                    if ($window_filter === 'all') {
                        echo 'All Counters';
                    } elseif ($window_filter === 'unassigned') {
                        echo 'Unassigned';
                    } else {
                        $selected_window_name = 'Selected Counter';
                        foreach ($windows as $w) {
                            if ((string)$w['id'] === (string)$window_filter) {
                                $selected_window_name = e($w['name']);
                                break;
                            }
                        }
                        echo $selected_window_name;
                    }
                ?></strong>
            </span>
        </div>
    </div>

    <!-- ── Queue table(s), grouped by counter ──────────────────────────────── -->
<?php if ($db_error): ?>
    <section class="queue-section">
        <div class="empty-state empty-state--error">
            <p><?= e($db_error) ?></p>
        </div>
    </section>
<?php elseif (empty($tickets)): ?>
        <section class="queue-section" aria-label="Queue tickets">
            <div class="empty-state">
                <h3>No tickets found</h3>
                <p>There are no tickets that match your current filters.</p>
                <a href="?type=all&window=all" class="btn btn-sm">Reset Filters</a>
            </div>
        </section>
    <?php elseif (isset($grouped)): ?>
        <?php foreach ($grouped as $window_id => $group): ?>
        <section class="queue-section ql-counter-section" aria-labelledby="counter-heading-<?= e($window_id) ?>">
            <div class="queue-section__head">
                <h2 id="counter-heading-<?= e($window_id) ?>">
                    <?= e($group['label']) ?>
                    <span class="count-badge teal"><?= count($group['tickets']) ?></span>
                </h2>
                <div class="sr-only">List of tickets for this counter.</div>
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
                            <td class="ql-num"><?= e($t['queue_number']) ?></td>
                            <td>
                                <?= e(trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))) ?: '—' ?>
                                <?php if ($t['priority']): ?>
                                    <span class="badge badge-priority">Priority</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['sr_code'] ?? '—') ?></td>
                            <td><span class="badge badge-type"><?= e($t['type']) ?></span></td>
                            <td>
                                <span class="badge <?= $status_classes[$t['status']] ?? 'badge-window' ?>">
                                    <?= e(str_replace('_', ' ', $t['status'])) ?>
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
</div><!-- /.app-shell -->


<link rel="stylesheet" href="/assets/css/queue-list.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>    