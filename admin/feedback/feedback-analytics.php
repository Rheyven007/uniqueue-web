<?php
// admin/feedback/feedback-analytics.php — Satisfaction rate & average ratings

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$is_super = !empty($_SESSION['is_super_admin']);
$office_id_filter = get_param('office_id', $is_super ? '' : ($_SESSION['office_id'] ?? ''));

// Fetch offices for filter dropdown
$offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Base SQL for analytics
$sql_base = "SELECT 
    COUNT(f.id) as total_feedback,
    AVG(f.rating) as average_rating,
    SUM(CASE WHEN f.rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
    SUM(CASE WHEN f.rating <= 2 THEN 1 ELSE 0 END) as negative_feedback
FROM feedbacks f
JOIN queue_tickets qt ON qt.id = f.ticket_id
JOIN offices o ON o.id = qt.office_id";

$params = [];
$where_clause = "";
if (!empty($office_id_filter)) {
    $where_clause = " WHERE qt.office_id = ?";
    $params[] = $office_id_filter;
}

$sql = $sql_base . $where_clause;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$analytics = $stmt->fetch();

$pageTitle = "Feedback Analytics";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="header-actions mb-6">
        <h1>Feedback Analytics</h1>
        <div class="report-filters">
            <form method="GET" class="d-flex gap-3">
                <?php if ($is_super): ?>
                <select name="office_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $office_id_filter == $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                    <input type="hidden" name="office_id" value="<?= e($office_id_filter) ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($analytics['total_feedback'] > 0): ?>
    <div class="grid-3 mb-8">
        <div class="stat-card">
            <small>Total Feedback</small>
            <strong><?= (int)$analytics['total_feedback'] ?></strong>
        </div>
        <div class="stat-card">
            <small>Average Rating</small>
            <strong><?= round($analytics['average_rating'], 2) ?> / 5</strong>
        </div>
        <div class="stat-card">
            <small>Satisfaction Rate</small>
            <strong><?= round(($analytics['positive_feedback'] / $analytics['total_feedback']) * 100, 1) ?>%</strong>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h2 class="section-title mb-0">Rating Distribution</h2>
        </div>
        <div class="card-body">
            <?php
            $rating_distribution = $pdo->prepare("SELECT rating, COUNT(*) as count FROM feedbacks f JOIN queue_tickets qt ON qt.id = f.ticket_id " . $where_clause . " GROUP BY rating ORDER BY rating DESC");
            $rating_distribution->execute($params);
            $distribution = $rating_distribution->fetchAll();
            
            foreach ($distribution as $dist): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><?= str_repeat('★', $dist['rating']) ?><?= str_repeat('☆', 5 - $dist['rating']) ?></span>
                    <span><?= $dist['count'] ?> (<?= round(($dist['count'] / $analytics['total_feedback']) * 100, 1) ?>%)</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No feedback data available for the selected criteria yet.</p>
    </div>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="../admin.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>