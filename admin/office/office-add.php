<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?, ?)");
        $stmt->execute([$name, $is_active]);
        redirect('/admin/office/office-list.php');
    }
}

$pageTitle = "Add Office";
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-container">
    <h1>Add New Office</h1>
    <form method="POST" action="office-add.php">
        <div class="form-group">
            <label for="name">Office Name</label>
            <input type="text" id="name" name="name" required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="is_active">Initial Status</label>
            <select name="is_active" id="is_active" class="form-control">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Office</button>
            <a href="office-list.php">Cancel</a>
        </div>
    </form>
</div>

<link rel="stylesheet" href="/assets/css/offices-add.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>