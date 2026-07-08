<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

// ── Office scope ──────────────────────────────────────────────────────────────
// Office admins are locked to their own office.
// Super-admins (no office_id in session) can pick any office.
$session_office_id = $_SESSION['office_id'] ?? null;

if ($session_office_id) {
    // Only fetch this one office (used for display; office_id is forced server-side)
    $stmt_office = $pdo->prepare("SELECT id, name FROM offices WHERE id = ? AND is_active = 1");
    $stmt_office->execute([$session_office_id]);
    $locked_office = $stmt_office->fetch();

    if (!$locked_office) redirect('/auth/logout.php');
    $offices = [];   // not needed for the dropdown
} else {
    $locked_office = null;
    $offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $speed   = $_POST['speed'];
    $doc_ids = $_POST['documents'] ?? [];

    // Queue type: walkin | appointment | both
    $queue_type = $_POST['queue_type'] ?? 'walkin';
    if (!in_array($queue_type, ['walkin', 'appointment', 'both'], true)) {
        $queue_type = 'walkin';
    }

    // Staff member assigned to this counter (optional)
    $staff_id = !empty($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;

    // Force office_id from session for office admins; use POST value for super-admins
    $office_id = $session_office_id ?? ($_POST['office_id'] ?? null);

    if (!$office_id) {
        $error = "No office selected.";
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO windows (name, office_id, speed, status, queue_type) VALUES (?, ?, ?, 'closed', ?)");
            $stmt->execute([$name, $office_id, $speed, $queue_type]);
            $window_id = $pdo->lastInsertId();

            if (!empty($doc_ids)) {
                $stmtDoc = $pdo->prepare("INSERT INTO window_document (window_id, document_id) VALUES (?, ?)");
                foreach ($doc_ids as $doc_id) {
                    $stmtDoc->execute([$window_id, $doc_id]);
                }
            }

            // Assign the selected staff member to this window (must belong to the same office)
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
            $error = "Error saving counter: " . $e->getMessage();
        }
    }
}

// For the document list: office admins only see their own office's documents.
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

// Staff list: office admins only see their own office's active staff.
if ($session_office_id) {
    $stmt_staff = $pdo->prepare("
        SELECT id, name, position
        FROM staff
        WHERE office_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $stmt_staff->execute([$session_office_id]);
} else {
    $stmt_staff = $pdo->query("
        SELECT s.id, s.name, s.position, o.name as office_name
        FROM staff s
        JOIN offices o ON s.office_id = o.id
        WHERE s.status = 'active'
        ORDER BY o.name ASC, s.name ASC
    ");
}
$staff_members = $stmt_staff->fetchAll();

$pageTitle = "Add Counter";
include __DIR__ . '/../../includes/header.php';
?>

<div class="req-wrap">

<div class="req-topbar">
    <div>
        <h1>Add Service Counter</h1>
        <p>Fill in the details to create a new service window.</p>
    </div>
    <a href="counter-list.php" class="btn btn-ghost">← Back to Counters</a>
</div>

<?php if (isset($error)): ?>
    <div class="req-alert req-alert--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="req-form-card">
    <form method="POST">
        <div class="form-group">
            <label>Counter/Window Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Window 1" required>
        </div>

        <?php if ($locked_office): ?>
            <?php /* Office admin: show read-only office name, pass value as hidden */ ?>
            <div class="form-group">
                <label>Office</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($locked_office['name']) ?>" disabled>
                <input type="hidden" name="office_id" value="<?= (int)$locked_office['id'] ?>">
            </div>
        <?php else: ?>
            <?php /* Super-admin: full dropdown */ ?>
            <div class="form-group">
                <label>Office</label>
                <select name="office_id" class="form-control" required>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Relative Processing Speed</label>
            <select name="speed" class="form-control">
                <option value="normal">Normal</option>
                <option value="fast">Fast</option>
                <option value="slow">Slow</option>
            </select>
        </div>

        <div class="form-group">
            <label>Queue Type</label>
            <select name="queue_type" id="queue_type" class="form-control">
                <option value="walkin">Walk-in</option>
                <option value="appointment">Appointment</option>
                <option value="both">Both</option>
            </select>
        </div>

        <div class="form-group">
            <label>Assigned Staff</label>
            <select name="staff_id" class="form-control">
                <option value="">— Unassigned —</option>
                <?php foreach ($staff_members as $s): ?>
                    <option value="<?= $s['id'] ?>">
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
                        <input type="checkbox" name="documents[]" value="<?= $d['id'] ?>">
                        <?php if (!$session_office_id): ?>
                            <span class="doc-office-tag">[<?= htmlspecialchars($d['office_name']) ?>]</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($d['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Counter</button>
            <a href="counter-list.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
</div>
<link rel="stylesheet" href="/assets/css/requirements-manage.css">
<link rel="stylesheet" href="/assets/css/counter-add.css">
<?php include __DIR__ . '/../../includes/footer.php'; ?>