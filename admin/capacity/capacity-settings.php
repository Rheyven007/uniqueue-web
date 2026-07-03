<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

// If regular admin, they can only manage their own office. Super admin sees all.
$target_office_id = $_SESSION['office_id'] ?? null;

if (!$target_office_id) {
    // If super admin hasn't picked an office, fetch the first active one or show a list
    $stmt = $pdo->query("SELECT id FROM offices WHERE is_active = 1 LIMIT 1");
    $target_office_id = $stmt->fetchColumn();
}

// Fetch existing config or create default
$stmt = $pdo->prepare("SELECT * FROM office_configs WHERE office_id = ?");
$stmt->execute([$target_office_id]);
$config = $stmt->fetch();

if (!$config) {
    // Insert default config if none exists
    $stmt = $pdo->prepare("INSERT INTO office_configs (office_id) VALUES (?)");
    $stmt->execute([$target_office_id]);
    $stmt = $pdo->prepare("SELECT * FROM office_configs WHERE office_id = ?");
    $stmt->execute([$target_office_id]);
    $config = $stmt->fetch();
}

$office = $pdo->prepare("SELECT name FROM offices WHERE id = ?");
$office->execute([$target_office_id]);
$office_name = $office->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $daily_capacity = (int)$_POST['daily_capacity'];
    $walkin_enabled = isset($_POST['walkin_enabled']) ? 1 : 0;
    $appointment_enabled = isset($_POST['appointment_enabled']) ? 1 : 0;
    $priority_enabled = isset($_POST['priority_enabled']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE office_configs SET 
        start_time = ?, end_time = ?, daily_capacity = ?, 
        walkin_enabled = ?, appointment_enabled = ?, priority_enabled = ? 
        WHERE office_id = ?");
    
    if ($stmt->execute([$start_time, $end_time, $daily_capacity, $walkin_enabled, $appointment_enabled, $priority_enabled, $target_office_id])) {
        $success = "Settings updated successfully.";
        // Refresh config data
        $config = array_merge($config, $_POST);
    }
}

$pageTitle = "Capacity Settings - " . $office_name;
include __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <h1>Capacity Settings: <?= htmlspecialchars($office_name) ?></h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" class="form-container shadow-sm">
        <div class="grid-2">
            <div class="form-group">
                <label>Operating Hours (Start)</label>
                <input type="time" name="start_time" value="<?= $config['start_time'] ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Operating Hours (End)</label>
                <input type="time" name="end_time" value="<?= $config['end_time'] ?>" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label>Daily Total Capacity</label>
            <input type="number" name="daily_capacity" value="<?= $config['daily_capacity'] ?>" class="form-control" min="1" required>
            <small>Total tickets allowed per day across all types.</small>
        </div>

        <hr>

        <div class="form-group-checkbox">
            <label><input type="checkbox" name="walkin_enabled" <?= $config['walkin_enabled'] ? 'checked' : '' ?>> Enable Walk-in Queuing</label>
        </div>
        
        <div class="form-group-checkbox">
            <label><input type="checkbox" name="appointment_enabled" <?= $config['appointment_enabled'] ? 'checked' : '' ?>> Enable Appointments</label>
        </div>

        <div class="form-group-checkbox">
            <label><input type="checkbox" name="priority_enabled" <?= $config['priority_enabled'] ? 'checked' : '' ?>> Enable Priority Lane (PWD/Senior/Pregnant)</label>
        </div>

        <div class="actions mt-4">
            <button type="submit" class="btn btn-primary">Save Config</button>
        </div>
    </form>
</div>
<link rel="stylesheet" href="/assets/css/capacity-settings.css">

<?php include __DIR__ . '/../../includes/footer.php'; ?>