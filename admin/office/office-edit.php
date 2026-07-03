<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$office_id = $_GET['id'] ?? null;
if (!$office_id) {
    redirect('/admin/office/office-list.php');
}

$stmt = $pdo->prepare("SELECT * FROM offices WHERE id = ?");
$stmt->execute([$office_id]);
$office = $stmt->fetch();

if (!$office) {
    redirect('/admin/office/office-list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '');

    if ($name !== '') {
        $stmt = $pdo->prepare("UPDATE offices SET name = ?, is_active = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $is_active, $description, $office_id]);
        redirect('/admin/office/office-list.php');
    }
}

$pageTitle = "Edit Office";
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-container">
    <h1>Edit Office: <?= htmlspecialchars($office['name']) ?></h1>
    <form method="POST" action="office-edit.php?id=<?= $office['id'] ?>">
        <div class="form-group">
            <label for="name">Office Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($office['name']) ?>" required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($office['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="is_active">Status</label>
            <select name="is_active" id="is_active" class="form-control">
                <option value="1" <?= $office['is_active'] ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= !$office['is_active'] ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Update Office</button>
            <a href="office-list.php">Cancel</a>
        </div>
    </form>
</div>

<link rel="stylesheet" href="/assets/css/admin-offices-edit.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>