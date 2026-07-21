<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');


$window_id = $_SESSION['window_id'];
$office_id = $_SESSION['office_id'];

// CHECK KUNG NAKA-PAUSE ANG WINDOW
$stmt = $pdo->prepare("
    SELECT is_paused
    FROM windows
    WHERE id = ?
");

$stmt->execute([$window_id]);

$window = $stmt->fetch(PDO::FETCH_ASSOC);


try {
    // RESUME PAUSED SERVICE
    if (!empty($window['is_paused'])) {

        $stmt = $pdo->prepare("
            UPDATE windows
            SET is_paused = 0
            WHERE id = ?
        ");

        $stmt->execute([$window_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Service resumed.'
        ]);

        exit;
    }
        // CHECK KUNG MAY NAKA-IN PROGRESS NA
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

        if ($stmt->fetch()) {

            echo json_encode([
                'success' => false,
                'message' => 'There is already an active transaction.'
            ]);

            exit;
        }


        // KUNIN ANG UNANG WAITING CUSTOMER
        $stmt = $pdo->prepare("
            SELECT id
            FROM queue_tickets
            WHERE office_id = ?
            AND window_id = ?
            AND status = 'waiting'
            ORDER BY priority DESC, joined_at ASC
            LIMIT 1
        ");

        $stmt->execute([
            $office_id,
            $window_id
        ]);

        $ticket = $stmt->fetch();

        if (!$ticket) {

            echo json_encode([
                'success' => false,
                'message' => 'No waiting customer.'
            ]);

            exit;
        }

    // START SERVICE
    $stmt = $pdo->prepare("
        UPDATE queue_tickets
        SET
            status = 'in_progress',
            called_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $ticket['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Service started.'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}