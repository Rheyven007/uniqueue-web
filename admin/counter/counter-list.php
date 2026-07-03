<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

// If the logged-in user is scoped to an office, only show that office's windows.
// Super-admins (no office_id in session) see all.
$session_office_id = $_SESSION['office_id'] ?? null;

if ($session_office_id) {
    $stmt = $pdo->prepare("
        SELECT w.*, o.name as office_name
        FROM windows w
        JOIN offices o ON w.office_id = o.id
        WHERE w.office_id = ?
        ORDER BY w.name ASC
    ");
    $stmt->execute([$session_office_id]);
} else {
    $stmt = $pdo->query("
        SELECT w.*, o.name as office_name
        FROM windows w
        JOIN offices o ON w.office_id = o.id
        ORDER BY o.name ASC, w.name ASC
    ");
}
$counters = $stmt->fetchAll();

$pageTitle = "Manage Counters";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

    <div class="req-topbar">
        <div>
            <h1>Service Windows / Counters</h1>
            <p>Manage service windows and their document assignments.</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
            <a href="/admin/queue/office-dashboard.php" class="btn btn-ghost">← Dashboard</a>
            <a href="counter-add.php" class="btn btn-primary">Add New Counter</a>
        </div>
    </div>

    <div class="req-table-wrap">
    <table class="req-table">
        <thead>
            <tr>
                <th>Office</th>
                <th>Counter Name</th>
                <th>Processing Speed</th>
                <th>Queue Type</th>
                <th>Status</th>
                <th class="th-actions">Actions</th>

            </tr>
        </thead>
        <tbody>
            <?php foreach ($counters as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['office_name']) ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst($c['speed']) ?></span></td>
                    <td>
                        <?php
                            $queueLabels = ['walkin' => 'Walk-in', 'appointment' => 'Appointment', 'both' => 'Both'];
                            $queueType = $c['queue_type'] ?? 'walkin';
                        ?>
                        <span class="badge badge-info"><?= htmlspecialchars($queueLabels[$queueType] ?? ucfirst($queueType)) ?></span>
                        <?php if ($queueType !== 'walkin' && !empty($c['appointment_date'])): ?>
                            <br><small><?= htmlspecialchars($c['appointment_date']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-indicator <?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>

                    <td class="td-actions">
                        <a href="counter-edit.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                        <button class="btn-sm btn-toggle-counter" data-id="<?= $c['id'] ?>" data-status="<?= $c['status'] ?>">
                            <?= $c['status'] === 'open' ? 'Close Window' : 'Open Window' ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<link rel="stylesheet" href="/assets/css/counter-list.css">
<script src="/assets/js/counter-manage.js" defer></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>