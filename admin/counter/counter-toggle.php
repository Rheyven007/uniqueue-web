<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

// Must be FIRST — before any auth redirect can fire
header('Content-Type: application/json');

// Use the same auth pattern as all other admin pages.
// is_admin_logged_in() is defined in functions.php (included via session.php).
if (!is_admin_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id             = $_POST['id'] ?? null;
$current_status = trim($_POST['status'] ?? '');

if (!$id || !in_array($current_status, ['open', 'closed'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters.']);
    exit;
}

// ── Office-admin scope check ──────────────────────────────────────────────────
// Office admins may only toggle windows in their own office.
// Super-admins (is_super_admin in session) may toggle any window.
$session_office_id = $_SESSION['office_id'] ?? null;
$is_super          = !empty($_SESSION['is_super_admin']);

if (!$is_super && $session_office_id) {
    $check = $pdo->prepare("SELECT id FROM windows WHERE id = ? AND office_id = ?");
    $check->execute([$id, $session_office_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: window does not belong to your office.']);
        exit;
    }
}

$new_status = ($current_status === 'open') ? 'closed' : 'open';

$stmt = $pdo->prepare("UPDATE windows SET status = ? WHERE id = ?");
if ($stmt->execute([$new_status, $id])) {
    echo json_encode(['success' => true, 'new_status' => $new_status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
}
exit;