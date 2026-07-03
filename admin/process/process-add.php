<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

// Fetch active offices for the dropdown
$offices = $pdo->query("SELECT id, name FROM offices WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $office_id = $_POST['office_id'] ?? null;
    $estimated_time = $_POST['estimated_time'] ?? 10;

    if ($name && $office_id) {
        $stmt = $pdo->prepare("INSERT INTO processes (office_id, name, estimated_time) VALUES (?, ?, ?)");
        $stmt->execute([$office_id, $name, $estimated_time]);
        redirect('/admin/process/process-list.php');
    }
}

$pageTitle = "Add Process/Event";
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-container">
    <h1>Add New Process/Event</h1>
    <form method="POST" action="process-add.php">
        <div class="form-group">
            <label for="office_id">Office</label>
            <select name="office_id" id="office_id" required class="form-control">
                <option value="">Select an Office</option>
                <?php foreach ($offices as $office): ?>
                    <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="name">Process/Event Name</label>
            <input type="text" id="name" name="name" required class="form-control" placeholder="e.g. Enrollment, Document Request">
        </div>
        
        <div class="form-group">
            <label for="estimated_time">Estimated Time (minutes)</label>
            <input type="number" id="estimated_time" name="estimated_time" value="10" min="1" required class="form-control">
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Process</button>
            <a href="process-list.php">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>