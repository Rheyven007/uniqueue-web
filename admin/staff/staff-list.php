<?php
// admin/staff/staff-list.php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id = $_SESSION['office_id'];

$search = trim($_GET['search'] ?? '');

$sql = "
SELECT
    s.*,
    o.name AS office_name,
    w.name AS window_name
FROM staff s
LEFT JOIN offices o ON s.office_id = o.id
LEFT JOIN windows w ON s.window_id = w.id
WHERE s.office_id = ?
";

$params = [$office_id];

if ($search != '') {
    $sql .= " AND (
        s.name LIKE ?
        OR s.username LIKE ?
        OR s.position LIKE ?
    )";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll();

$pageTitle = "Manage Staff";

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/staff.css">

<div class="staff-wrap">

    <div class="staff-header">

        <div>
            <h1>Staff Management</h1>
            <p>Manage office staff accounts.</p>
        </div>

        <a href="staff-add.php" class="btn btn-primary">
            + Add Staff
        </a>

    </div>

    <form method="GET" class="staff-search">

        <input
            type="text"
            name="search"
            placeholder="Search staff..."
            value="<?= htmlspecialchars($search) ?>"
        >

        <button type="submit" class="btn btn-green">
            Search
        </button>

    </form>

    <div class="table-card">

        <table class="staff-table">

            <thead>

            <tr>

                <th>Name</th>

                <th>Username</th>

                <th>Position</th>

                <th>Window</th>

                <th>Status</th>

                <th width="180">Action</th>

            </tr>

            </thead>

            <tbody>

            <?php if(empty($staff)): ?>

                <tr>

                    <td colspan="6" class="empty">
                        No staff found.
                    </td>

                </tr>

            <?php endif; ?>

            <?php foreach($staff as $row): ?>

                <tr>

                    <td>

                        <?= htmlspecialchars($row['name']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['username']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['position']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['window_name'] ?? '-') ?>

                    </td>

                    <td>

                        <?php if($row['status'] == 'active'): ?>

                            <span class="badge badge-success">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge badge-danger">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                            href="staff-edit.php?id=<?= $row['id'] ?>"
                            class="btn btn-warning btn-sm">

                            Edit

                        </a>

                        <a
                            href="staff-delete.php?id=<?= $row['id'] ?>"
                            class="btn btn-danger btn-sm"
                            onclick="return confirm('Delete this staff?')">

                            Delete

                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>