<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$window_id = $_SESSION['window_id'];
$office_id = $_SESSION['office_id'];

try {



    // Get next queue
    $stmt = $pdo->prepare("
        SELECT id
        FROM queue_tickets
        WHERE office_id = ?
        AND window_id = ?
        AND status = 'waiting'
        ORDER BY priority DESC, joined_at ASC
        LIMIT 1 OFFSET 0
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

    /*
    |--------------------------------------------------------------------------
    | CANCEL CURRENT WAITING TICKET
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE queue_tickets
        SET status = 'cancelled'
        WHERE id = ?
    ");

    $stmt->execute([
        $ticket['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Queue cancelled.'
    ]);

    exit;

    }catch(PDOException $e){

        echo json_encode([
            'success'=>false,
            'message'=>$e->getMessage()
        ]);

    }

