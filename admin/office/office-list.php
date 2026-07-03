<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_super_admin();

$stmt = $pdo->query("SELECT * FROM offices ORDER BY name ASC");
$offices = $stmt->fetchAll();

$pageTitle = "Manage Offices";
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="header-actions">
        <h1>Offices</h1>
        <a href="office-add.php" class="btn btn-primary">Add New Office</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($offices as $office): ?>
                <tr>
                    <td><?= htmlspecialchars($office['name']) ?></td>
                    <td>
                        <span class="badge <?= $office['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $office['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <a href="office-edit.php?id=<?= $office['id'] ?>" class="btn-sm">Edit</a>
                        <button class="btn-sm btn-toggle" data-id="<?= $office['id'] ?>" data-status="<?= $office['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $office['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<link rel="stylesheet" href="/assets/css/offices-list.css">
<script src="/assets/js/office-manage.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>