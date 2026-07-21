<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$window_id = $_SESSION['window_id'];

try {

    // Siguraduhing may active transaction bago mag-pause
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE window_id = ?
        AND status = 'in_progress'
        LIMIT 1
    ");

    $stmt->execute([$window_id]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'No active service found.'
        ]);
        exit;
    }

    // I-mark lang ang window bilang paused
    $stmt = $pdo->prepare("
        UPDATE windows
        SET is_paused = 1
        WHERE id = ?
    ");

    $stmt->execute([$window_id]);

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