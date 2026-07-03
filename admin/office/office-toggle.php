<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office_id = $_POST['id'] ?? null;
    $current_is_active = $_POST['is_active'] ?? null; // Expecting 0 or 1

    if ($office_id !== null && ($current_is_active === '0' || $current_is_active === '1')) {
        $new_is_active = ($current_is_active === '1') ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE offices SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$new_is_active, $office_id])) {
            $response['success'] = true;
            $response['message'] = 'Office status updated successfully.';
            $response['new_status'] = $new_is_active;
        } else {
            $response['message'] = 'Failed to update office status.';
        }
    }
}
echo json_encode($response);