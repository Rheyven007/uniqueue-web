<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$process_id = $_GET['id'] ?? null;
if (!$process_id) {
    redirect('/admin/process/process-list.php');
}

$stmt = $pdo->prepare("SELECT * FROM processes WHERE id = ?");
$stmt->execute([$process_id]);
$process = $stmt->fetch();

if (!$process) {
    redirect('/admin/process/process-list.php');
}

// Fetch active offices for the dropdown
$offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $office_id = $_POST['office_id'] ?? null;
    $estimated_time = $_POST['estimated_time'] ?? 10;

    if ($name && $office_id) {
        $stmt = $pdo->prepare("UPDATE processes SET office_id = ?, name = ?, estimated_time = ? WHERE id = ?");
        $stmt->execute([$office_id, $name, $estimated_time, $process_id]);
        redirect('/admin/process/process-list.php');
    }
}

$pageTitle = "Edit Process/Event";
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-container">
    <h1>Edit Process/Event: <?= htmlspecialchars($process['name']) ?></h1>
    <form method="POST" action="process-edit.php?id=<?= $process['id'] ?>">
        <div class="form-group">
            <label for="office_id">Office</label>
            <select name="office_id" id="office_id" required class="form-control">
                <option value="">Select an Office</option>
                <?php foreach ($offices as $office): ?>
                    <option value="<?= $office['id'] ?>" <?= ($office['id'] == $process['office_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($office['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="name">Process/Event Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($process['name']) ?>" required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="estimated_time">Estimated Time (minutes)</label>
            <input type="number" id="estimated_time" name="estimated_time" value="<?= (int)$process['estimated_time'] ?>" min="1" required class="form-control">
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Update Process</button>
            <a href="process-list.php">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>