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

<div class="app-shell">

    <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>

    <div class="od-wrap">

        <div class="staff-wrap" style="padding:0;margin:0;animation:none;">



    <div class="staff-header">

        <div class="staff-header__left">
            <div class="staff-header__text">
                <h1>
                    Staff Management
                    <span class="staff-count"><?= count($staff) ?></span>
                </h1>
                <p>Manage office staff accounts.</p>
            </div>
        </div>

        <div class="staff-header__actions">
            <button class="btn btn-ghost btn-sm" onclick="window.location.reload()" aria-label="Refresh list">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6"/>
                    <path d="M21.34 15.57a10 10 0 1 1-.4-4.57"/>
                </svg>
                Refresh
            </button>
            <a href="staff-add.php" class="btn btn-green">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14"/><path d="M5 12h14"/>
                </svg>
                Add Staff
            </a>
        </div>

    </div>

    <form method="GET" class="search-bar" role="search">

        <div class="search-bar__field">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
           
            <input
                id="staff-search"
                type="text"
                name="search"
                placeholder="Search by name, username, or position..."
                value="<?= htmlspecialchars($search) ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary btn-search" aria-label="Search">Search</button>

    </form>

    <!-- Live region: announces search/save/delete results for assistive tech -->
    <div class="sr-only" role="status" aria-live="polite" id="staff-status-region"></div>

    <div class="ql-table-wrap">

        <table class="ql-table">

            <thead>

            <tr>

                <th>Name</th>

                <th>Username</th>

                <th>Position</th>

                <th>Window</th>

                <th>Status</th>

                <th class="td-actions" width="180">Actions</th>

            </tr>

            </thead>

            <tbody>

            <?php if(empty($staff)): ?>

                <tr>

                    <td colspan="6" class="empty">
                        <?php if ($search !== ''): ?>
                            No staff match "<?= htmlspecialchars($search) ?>". <a href="staff-list.php">Clear search</a>
                        <?php else: ?>
                            No staff found. <a href="staff-add.php">Add the first one.</a>
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endif; ?>

            <?php foreach($staff as $row): ?>

                <tr>

                    <td>
                        <div class="staff-name">
                            <span class="staff-avatar">
                                <?= strtoupper(substr($row['name'], 0, 1)) ?>
                            </span>
                            <?= htmlspecialchars($row['name']) ?>
                        </div>
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
                        <div class="td-actions">
                            <a
                                href="staff-edit.php?id=<?= $row['id'] ?>"
                                class="btn btn-warning btn-sm"
                                title="Edit <?= htmlspecialchars($row['name']) ?>">

                                Edit

                            </a>

                            <a
                                href="staff-delete.php?id=<?= $row['id'] ?>"
                                class="btn btn-danger btn-sm"
                                title="Delete <?= htmlspecialchars($row['name']) ?>"
                                onclick="return confirm('Delete this staff account? This action cannot be undone.')">

                                Delete

                            </a>
                        </div>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div><!-- /.staff-wrap -->

    </div><!-- /.od-wrap -->
</div><!-- /.app-shell -->

<?php include __DIR__ . '/../../includes/footer.php'; ?>