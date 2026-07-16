<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$window_id = $_SESSION['window_id'];
$office_id = $_SESSION['office_id'];

try {

    // Hanapin ang current ticket na nasa in_progress
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE office_id = ?
        AND window_id = ?
        AND status = 'in_progress'
        LIMIT 1
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'No active service found.'
        ]);
        exit;
    }

    // Ibalik sa waiting
    $stmt = $pdo->prepare("
        UPDATE queue_tickets
        SET
            status = 'waiting',
            called_at = NULL
        WHERE id = ?
    ");

    $stmt->execute([
        $ticket['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Service paused successfully.'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}