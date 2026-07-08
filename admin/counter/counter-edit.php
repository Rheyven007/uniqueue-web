<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

$window_id = $_GET['id'] ?? null;
if (!$window_id) redirect('/admin/counter/counter-list.php');

// ── Office scope ──────────────────────────────────────────────────────────────
$session_office_id = $_SESSION['office_id'] ?? null;

// Fetch the window — office admins may only edit windows in their own office
if ($session_office_id) {
    $stmt = $pdo->prepare("SELECT * FROM windows WHERE id = ? AND office_id = ?");
    $stmt->execute([$window_id, $session_office_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM windows WHERE id = ?");
    $stmt->execute([$window_id]);
}
$window = $stmt->fetch();
if (!$window) redirect('/admin/counter/counter-list.php');

// Fetch documents currently assigned to this window
$stmt_assigned_docs = $pdo->prepare("SELECT document_id FROM window_document WHERE window_id = ?");
$stmt_assigned_docs->execute([$window_id]);
$assigned_doc_ids = array_column($stmt_assigned_docs->fetchAll(), 'document_id');

// Fetch the staff member currently assigned to this window (if any)
$stmt_assigned_staff = $pdo->prepare("SELECT id FROM staff WHERE window_id = ? LIMIT 1");
$stmt_assigned_staff->execute([$window_id]);
$assigned_staff_row = $stmt_assigned_staff->fetch();
$assigned_staff_id = $assigned_staff_row ? (int)$assigned_staff_row['id'] : null;

// Office list — super-admins can reassign; office admins cannot
if ($session_office_id) {
    $stmt_office = $pdo->prepare("SELECT id, name FROM offices WHERE id = ? AND is_active = 1");
    $stmt_office->execute([$session_office_id]);
    $locked_office = $stmt_office->fetch();
    $offices = [];
} else {
    $locked_office = null;
    $offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

// Document list — office admins only see their own office's documents
if ($session_office_id) {
    $stmt_docs = $pdo->prepare("
        SELECT d.id, d.name, o.name as office_name
        FROM documents d
        JOIN offices o ON d.office_id = o.id
        WHERE d.office_id = ?
        ORDER BY d.name ASC
    ");
    $stmt_docs->execute([$session_office_id]);
} else {
    $stmt_docs = $pdo->query("
        SELECT d.id, d.name, o.name as office_name
        FROM documents d
        JOIN offices o ON d.office_id = o.id
        ORDER BY o.name ASC, d.name ASC
    ");
}
$documents = $stmt_docs->fetchAll();

// Staff list: office admins only see their own office's active staff
// (the currently-assigned staff member is always included so they show up as selected).
if ($session_office_id) {
    $stmt_staff = $pdo->prepare("
        SELECT id, name, position
        FROM staff
        WHERE office_id = ? AND (status = 'active' OR id = ?)
        ORDER BY name ASC
    ");
    $stmt_staff->execute([$session_office_id, $assigned_staff_id ?: 0]);
} else {
    $stmt_staff = $pdo->prepare("
        SELECT s.id, s.name, s.position, o.name as office_name
        FROM staff s
        JOIN offices o ON s.office_id = o.id
        WHERE s.status = 'active' OR s.id = ?
        ORDER BY o.name ASC, s.name ASC
    ");
    $stmt_staff->execute([$assigned_staff_id ?: 0]);
}
$staff_members = $stmt_staff->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $speed     = $_POST['speed'] ?? 'normal';
    $est_time  = $_POST['est_time'] !== '' ? (int)$_POST['est_time'] : null;
    $doc_ids   = $_POST['documents'] ?? [];

    // Queue type: walkin | appointment | both
    $queue_type = $_POST['queue_type'] ?? 'walkin';
    if (!in_array($queue_type, ['walkin', 'appointment', 'both'], true)) {
        $queue_type = 'walkin';
    }

    // Staff member assigned to this counter (optional)
    $staff_id = !empty($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;

    // Force office_id from session for office admins; use POST value for super-admins
    $office_id = $session_office_id ?? ($_POST['office_id'] ?? null);

    if ($name && $office_id) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE windows SET name = ?, office_id = ?, speed = ?, est_time = ?, queue_type = ? WHERE id = ?");
            $stmt->execute([$name, $office_id, $speed, $est_time, $queue_type, $window_id]);

            $stmt_delete_docs = $pdo->prepare("DELETE FROM window_document WHERE window_id = ?");
            $stmt_delete_docs->execute([$window_id]);

            if (!empty($doc_ids)) {
                $stmt_insert_doc = $pdo->prepare("INSERT INTO window_document (window_id, document_id) VALUES (?, ?)");
                foreach ($doc_ids as $doc_id) {
                    $stmt_insert_doc->execute([$window_id, $doc_id]);
                }
            }

            // Release whoever is currently assigned to this window, then assign the newly selected staff member
            $pdo->prepare("UPDATE staff SET window_id = NULL WHERE window_id = ?")->execute([$window_id]);

            if ($staff_id) {
                $stmtValidateStaff = $pdo->prepare("SELECT id FROM staff WHERE id = ? AND office_id = ?");
                $stmtValidateStaff->execute([$staff_id, $office_id]);
                if ($stmtValidateStaff->fetch()) {
                    $pdo->prepare("UPDATE staff SET window_id = ? WHERE id = ?")->execute([$window_id, $staff_id]);
                }
            }

            $pdo->commit();
            redirect('/admin/counter/counter-list.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating counter: " . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Counter";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

<div class="req-topbar">
    <div>
        <h1>Edit Service Counter</h1>
        <p><strong><?= htmlspecialchars($window['name']) ?></strong></p>
    </div>
    <a href="counter-list.php" class="btn btn-ghost">← Back to Counters</a>
</div>

<?php if (isset($error)): ?>
    <div class="req-alert req-alert--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="req-form-card">
    <form method="POST" action="counter-edit.php?id=<?= (int)$window['id'] ?>">
        <div class="form-group">
            <label>Counter/Window Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?= htmlspecialchars($window['name']) ?>" required>
        </div>

        <?php if ($locked_office): ?>
            <?php /* Office admin: office is fixed */ ?>
            <div class="form-group">
                <label>Office</label>
                <input type="text" class="form-control"
                       value="<?= htmlspecialchars($locked_office['name']) ?>" disabled>
                <input type="hidden" name="office_id" value="<?= (int)$locked_office['id'] ?>">
            </div>
        <?php else: ?>
            <?php /* Super-admin: can reassign to any office */ ?>
            <div class="form-group">
                <label>Office</label>
                <select name="office_id" class="form-control" required>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= $o['id'] ?>"
                            <?= ($o['id'] == $window['office_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($o['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Relative Processing Speed</label>
            <select name="speed" class="form-control">
                <option value="normal" <?= ($window['speed'] === 'normal') ? 'selected' : '' ?>>Normal</option>
                <option value="fast"   <?= ($window['speed'] === 'fast')   ? 'selected' : '' ?>>Fast</option>
                <option value="slow"   <?= ($window['speed'] === 'slow')   ? 'selected' : '' ?>>Slow</option>
            </select>
        </div>

        <div class="form-group">
            <label>Estimated Processing Time (minutes)</label>
            <input type="number" name="est_time" class="form-control" min="1"
                   value="<?= htmlspecialchars($window['est_time'] ?? '') ?>" placeholder="e.g. 10">
            <small>Average time it takes this counter to serve one ticket.</small>
        </div>

        <div class="form-group">
            <label>Queue Type</label>
            <select name="queue_type" id="queue_type" class="form-control">
                <option value="walkin" <?= ($window['queue_type'] === 'walkin') ? 'selected' : '' ?>>Walk-in</option>
                <option value="appointment" <?= ($window['queue_type'] === 'appointment') ? 'selected' : '' ?>>Appointment</option>
                <option value="both" <?= ($window['queue_type'] === 'both') ? 'selected' : '' ?>>Both</option>
            </select>
        </div>

        <div class="form-group">
            <label>Assigned Staff</label>
            <select name="staff_id" class="form-control">
                <option value="">— Unassigned —</option>
                <?php foreach ($staff_members as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($s['id'] == $assigned_staff_id) ? 'selected' : '' ?>>
                        <?php if (!$session_office_id): ?>[<?= htmlspecialchars($s['office_name']) ?>] <?php endif; ?>
                        <?= htmlspecialchars($s['name']) ?><?= $s['position'] ? ' — ' . htmlspecialchars($s['position']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>The staff member responsible for serving this counter.</small>
        </div>

        <div class="form-group">
            <label>Documents Handled by this Counter</label>

            <div class="checkbox-list">
                <?php foreach ($documents as $d): ?>
                    <label>
                        <input type="checkbox" name="documents[]" value="<?= $d['id'] ?>"
                               <?= in_array($d['id'], $assigned_doc_ids) ? 'checked' : '' ?>>
                        <?php if (!$session_office_id): ?>
                            <span class="doc-office-tag">[<?= htmlspecialchars($d['office_name']) ?>]</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($d['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Counter</button>
            <a href="counter-list.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
</div>

<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<link rel="stylesheet" href="/assets/css/counter-edit.css">
<?php include __DIR__ . '/../../includes/footer.php'; ?>