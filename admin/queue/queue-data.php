<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');

$office_id  = $_SESSION['office_id'];
$window_id  = $_SESSION['window_id'];
$staff_name = $_SESSION['staff_name'];

try {

    /*
    |--------------------------------------------------------------------------
    | STAFF & WINDOW INFO
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            w.id,
            w.name,
            w.status
        FROM windows w
        WHERE w.id = ?
        LIMIT 1
    ");

    $stmt->execute([$window_id]);

    $window = $stmt->fetch(PDO::FETCH_ASSOC);

    $staffInfo = [
        'staff_name'    => $staff_name,
        'window_id'     => $window['id'] ?? null,
        'window_name'   => $window['name'] ?? null,
        'window_status' => $window['status'] ?? null,
    ];

    /*
    |--------------------------------------------------------------------------
    | CURRENT SERVING
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            qt.*,
            s.first_name,
            s.last_name,
            s.sr_code
        FROM queue_tickets qt
        LEFT JOIN students s
            ON s.id = qt.student_id
        WHERE
            qt.office_id = ?
            AND qt.window_id = ?
           AND qt.status='in_progress'
        ORDER BY qt.called_at DESC
        LIMIT 1
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $currentTicket = $stmt->fetch(PDO::FETCH_ASSOC);

  
   
    /*
    |--------------------------------------------------------------------------
    | WAITING QUEUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            qt.*,
            s.first_name,
            s.last_name,
            s.sr_code
        FROM queue_tickets qt
        LEFT JOIN students s
            ON s.id = qt.student_id
        WHERE
            qt.office_id = ?
            AND qt.window_id = ?
            AND qt.status = 'waiting'
        ORDER BY
            qt.priority DESC,
            qt.joined_at ASC,
            qt.id ASC
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $waitingTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* -------------------------------------------------------
    DETERMINE NEXT TICKET
    -------------------------------------------------------- */

    $nextTicket = null;

    if ($currentTicket) {
        $nextTicket = $waitingTickets[0] ?? null;

        if ($nextTicket) {
            array_shift($waitingTickets);
        }
    }

    echo json_encode([
        "success"   => true,
        "office_id" => $office_id,
        "window_id" => $window_id,
        "staff"     => $staffInfo,
        "current"   => $currentTicket ?: null,
        "next"      => $nextTicket,
        "waiting"   => $waitingTickets
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}