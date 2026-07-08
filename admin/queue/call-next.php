<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$window_id = $_SESSION['window_id'];
$office_id = $_SESSION['office_id'];

try {

    // Check active ticket
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE window_id = ?
        AND office_id = ?
        AND status IN ('called','in_progress')
        LIMIT 1
    ");

    $stmt->execute([
        $window_id,
        $office_id
    ]);

    if ($stmt->fetch()) {

        echo json_encode([
            'success'=>false,
            'message'=>'Finish current transaction first.'
        ]);

        exit;
    }


    // Get next queue
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE office_id = ?
        AND status = 'waiting'
        AND (window_id IS NULL OR window_id = ?)
        ORDER BY priority DESC, joined_at ASC
        LIMIT 1
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $ticket = $stmt->fetch();


    if(!$ticket){

        echo json_encode([
            'success'=>false,
            'message'=>'No waiting queue.'
        ]);

        exit;
    }


    // Assign window and call
    $stmt = $pdo->prepare("
        UPDATE queue_tickets
        SET
            window_id = ?,
            status='called',
            called_at = NOW()
        WHERE id=?
    ");

    $stmt->execute([
        $window_id,
        $ticket['id']
    ]);


    echo json_encode([
        'success'=>true,
        'message'=>'Queue called.'
    ]);


}catch(PDOException $e){

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);

}