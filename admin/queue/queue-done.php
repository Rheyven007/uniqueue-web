<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$window_id = $_SESSION['window_id'];
$office_id = $_SESSION['office_id'];

try {

    // Hanapin ang kasalukuyang nasa service
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
            'message' => 'No active queue.'
        ]);

        exit;
    }

    // I-complete ang current customer
    $stmt = $pdo->prepare("
        UPDATE queue_tickets
        SET
            status = 'completed',
            done_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $ticket['id']
    ]);

    // Hanapin ang susunod na waiting customer
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE office_id = ?
          AND window_id = ?
          AND status = 'waiting'
        ORDER BY
            priority DESC,
            joined_at ASC,
            id ASC
        LIMIT 1
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $nextTicket = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kung may kasunod, automatic siyang magiging current serving
    if ($nextTicket) {

        $stmt = $pdo->prepare("
            UPDATE queue_tickets
            SET
                status = 'in_progress',
                called_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $nextTicket['id']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Transaction completed.'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}