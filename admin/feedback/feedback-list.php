<?php
// admin/feedback/feedback-list.php — View all feedback submissions

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$is_super = !empty($_SESSION['is_super_admin']);
$office_id_filter = get_param('office_id', $is_super ? '' : ($_SESSION['office_id'] ?? ''));

// Fetch offices for filter dropdown
$offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$sql = "SELECT f.*, qt.queue_number, qt.type, o.name as office_name, s.first_name, s.last_name, s.sr_code
        FROM feedbacks f
        JOIN queue_tickets qt ON qt.id = f.ticket_id
        JOIN offices o ON o.id = qt.office_id
        JOIN students s ON s.id = f.student_id";

$params = [];
if (!empty($office_id_filter)) {
    $sql .= " WHERE qt.office_id = ?";
    $params[] = $office_id_filter;
}
$sql .= " ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

$pageTitle = "Feedback List";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="header-actions mb-6">
        <h1>Student Feedback</h1>
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

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Office</th>
                        <th>Student</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($feedbacks)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No feedback submitted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $f): ?>
                        <tr>
                            <td class="font-bold"><?= e($f['queue_number']) ?></td>
                            <td><?= e($f['office_name']) ?></td>
                            <td><?= e($f['first_name'] . ' ' . $f['last_name']) ?> <br><small><?= e($f['sr_code']) ?></small></td>
                            <td>
                                <span class="rating-display">
                                    <?= str_repeat('★', $f['rating']) ?><?= str_repeat('☆', 5 - $f['rating']) ?>
                                </span>
                            </td>
                            <td><?= e($f['comment']) ?></td>
                            <td><?= format_datetime($f['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link rel="stylesheet" href="/assets/css/feedback-list.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>