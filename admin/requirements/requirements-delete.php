
<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_office_admin();

header('Content-Type: application/json');

$office_id      = $_SESSION['office_id'] ?? null;
$is_super_admin = !empty($_SESSION['is_super_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Ownership check: verify this requirement belongs to the admin's office
    if (!$is_super_admin) {
        $chk = $pdo->prepare("
            SELECT dr.id FROM document_requirements dr
            JOIN documents d ON dr.document_id = d.id
            WHERE dr.id = ? AND d.office_id = ?
        ");
        $chk->execute([$id, $office_id]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM document_requirements WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Could not delete requirement.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);