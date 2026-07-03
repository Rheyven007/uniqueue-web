<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $process_id = $_POST['id'] ?? null;

    if ($process_id !== null) {
        $stmt = $pdo->prepare("DELETE FROM processes WHERE id = ?");
        if ($stmt->execute([$process_id])) {
            $response['success'] = true;
            $response['message'] = 'Process deleted successfully.';
        } else {
            $response['message'] = 'Failed to delete process.';
        }
    }
}

echo json_encode($response);
exit;