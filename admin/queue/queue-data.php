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

    $windowInfo = null;

    if ($window_id) {

        $stmt = $pdo->prepare("
            SELECT
                id AS window_id,
                name AS window_name,
                status AS window_status
            FROM windows
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $window_id
        ]);

        $windowInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    }

    $staffInfo = [
        'staff_name'    => $staff_name,
        'window_id'     => $windowInfo['window_id'] ?? null,
        'window_name'   => $windowInfo['window_name'] ?? null,
        'window_status' => $windowInfo['window_status'] ?? null,
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
            AND qt.status IN ('called', 'in_progress')
        ORDER BY qt.called_at DESC
        LIMIT 1
    ");

    $stmt->execute([
        $office_id,
        $window_id
    ]);

    $currentTicket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentTicket) {
        $currentTicket = null;
    }

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
            AND (qt.window_id IS NULL OR qt.window_id = ?)
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

    echo json_encode([
        "success" => true,
        "office_id" => $office_id,
        "window_id" => $window_id,
        "staff" => $staffInfo,
        "current" => $currentTicket,
        "waiting" => $waitingTickets
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}