<?php
// admin/reports/reports-daily.php — Daily transaction report

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$date_filter = get_param('date', date('Y-m-d'));
$office_id   = get_param('office_id', $_SESSION['office_id'] ?? '');
$is_super    = !empty($_SESSION['is_super_admin']);

// Fetch offices for filter
$offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Stats Query
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting
    FROM queue_tickets 
    WHERE DATE(joined_at) = ?";

$params = [$date_filter];
if (!$is_super || !empty($office_id)) {
    $stats_sql .= " AND office_id = ?";
    $params[] = $office_id;
}

$stmt = $pdo->prepare($stats_sql);
$stmt->execute($params);
$stats = $stmt->fetch();

// Detailed Transactions Query
$list_sql = "SELECT qt.*, o.name as office_name, s.first_name, s.last_name, s.sr_code
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    JOIN students s ON s.id = qt.student_id
    WHERE DATE(qt.joined_at) = ?";

$list_params = [$date_filter];
if (!$is_super || !empty($office_id)) {
    $list_sql .= " AND qt.office_id = ?";
    $list_params[] = $office_id;
}
$list_sql .= " ORDER BY qt.joined_at ASC";

$stmt = $pdo->prepare($list_sql);
$stmt->execute($list_params);
$tickets = $stmt->fetchAll();

$pageTitle = "Daily Reports";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="header-actions mb-6">
        <h1>Daily Transactions</h1>
        <div class="report-filters">
            <form method="GET" class="d-flex gap-3">
                <input type="date" name="date" value="<?= e($date_filter) ?>" class="form-input">
                <?php if ($is_super): ?>
                <select name="office_id" class="form-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $office_id == $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn btn--primary">Filter</button>
            </form>
        </div>
    </div>

    <div class="grid-4 mb-8">
        <div class="stat-card">
            <small>Total Tickets</small>
            <strong><?= (int)$stats['total'] ?></strong>
        </div>
        <div class="stat-card">
            <small>Completed</small>
            <strong class="text-success"><?= (int)$stats['completed'] ?></strong>
        </div>
        <div class="stat-card">
            <small>Cancelled</small>
            <strong class="text-danger"><?= (int)$stats['cancelled'] ?></strong>
        </div>
        <div class="stat-card">
            <small>Still Waiting</small>
            <strong class="text-warning"><?= (int)$stats['waiting'] ?></strong>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="section-title mb-0">Transaction Log</h2>
            <div class="export-actions">
                <a href="export-excel.php?date=<?= $date_filter ?>&office_id=<?= $office_id ?>" class="btn btn--xs btn--outline">Export CSV</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Student</th>
                        <th>Office</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="font-bold"><?= e($t['queue_number']) ?></td>
                        <td><?= e($t['first_name'] . ' ' . $t['last_name']) ?> <br><small><?= e($t['sr_code']) ?></small></td>
                        <td><?= e($t['office_name']) ?></td>
                        <td><?= e(ucfirst($t['type'])) ?></td>
                        <td><span class="ticket-status-badge ticket-status-badge--<?= e($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
                        <td><?= format_datetime($t['joined_at'], 'h:i A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link rel="stylesheet" href="/assets/css/reports-daily.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>