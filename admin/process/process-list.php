<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$stmt = $pdo->query("
    SELECT p.*, o.name as office_name 
    FROM processes p 
    JOIN offices o ON p.office_id = o.id 
    ORDER BY o.name ASC, p.name ASC
");
$processes = $stmt->fetchAll();

$pageTitle = "Manage Processes";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="header-actions">
        <h1>Processes & Events</h1>
        <a href="process-add.php" class="btn btn-primary">Add New Process</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Office</th>
                <th>Process Name</th>
                <th>Estimated Time (mins)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($processes as $proc): ?>
                <tr>
                    <td><?= htmlspecialchars($proc['office_name']) ?></td>
                    <td><?= htmlspecialchars($proc['name']) ?></td>
                    <td><?= (int)$proc['estimated_time'] ?></td>
                    <td>
                        <a href="process-edit.php?id=<?= $proc['id'] ?>" class="btn-sm">Edit</a>
                        <button class="btn-sm btn-danger delete-process" data-id="<?= $proc['id'] ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<link rel="stylesheet" href="../admin.css">
<script src="/assets/js/process-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>