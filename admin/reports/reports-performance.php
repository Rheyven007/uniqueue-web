<?php
// admin/reports/reports-performance.php — Office performance metrics

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$is_super = !empty($_SESSION['is_super_admin']);
$office_id = $_SESSION['office_id'] ?? '';

// Aggregated metrics query
$perf_sql = "SELECT 
        o.name as office_name,
        COUNT(qt.id) as total_served,
        AVG(TIMESTAMPDIFF(MINUTE, qt.joined_at, qt.called_at)) as avg_wait_time,
        AVG(TIMESTAMPDIFF(MINUTE, qt.called_at, qt.done_at)) as avg_serve_time
    FROM offices o
    JOIN queue_tickets qt ON qt.office_id = o.id
    WHERE qt.status = 'done' AND qt.called_at IS NOT NULL AND qt.done_at IS NOT NULL";

if (!$is_super) {
    $perf_sql .= " AND o.id = " . (int)$office_id;
}

$perf_sql .= " GROUP BY o.id ORDER BY avg_wait_time ASC";

$metrics = $pdo->query($perf_sql)->fetchAll();

$pageTitle = "Performance Metrics";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <h1 class="mb-8">Office Performance</h1>

    <div class="grid-1 gap-6">
        <?php foreach ($metrics as $m): ?>
        <div class="card shadow-sm performance-row">
            <div class="card-body">
                <h2 class="section-title"><?= e($m['office_name']) ?></h2>
                <div class="grid-3 mt-4">
                    <div class="metric-box">
                        <label>Students Served</label>
                        <div class="metric-value"><?= (int)$m['total_served'] ?></div>
                    </div>
                    <div class="metric-box">
                        <label>Avg. Wait Time</label>
                        <div class="metric-value <?= $m['avg_wait_time'] > 20 ? 'text-danger' : 'text-success' ?>">
                            <?= round($m['avg_wait_time'], 1) ?> <small>mins</small>
                        </div>
                    </div>
                    <div class="metric-box">
                        <label>Avg. Processing Time</label>
                        <div class="metric-value">
                            <?= round($m['avg_serve_time'], 1) ?> <small>mins</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($metrics)): ?>
        <div class="empty-state">
            <p>No performance data available yet. Metrics are calculated from completed tickets.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../admin.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>