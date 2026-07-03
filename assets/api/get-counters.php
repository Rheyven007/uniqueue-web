<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_admin();

$office_id = get_param('office_id');
if (!$office_id) {
    json_response(['success' => false, 'message' => 'Office ID required'], 400);
}

try {
    // 1. Get Counters and their current serving ticket
    $stmt = $pdo->prepare("
        SELECT w.*, 
               qt.id as ticket_id, qt.queue_number, s.first_name, s.last_name
        FROM windows w
        LEFT JOIN queue_tickets qt ON qt.window_id = w.id AND qt.status IN ('called', 'in_progress')
        LEFT JOIN students s ON s.id = qt.student_id
        WHERE w.office_id = ?
        ORDER BY w.name ASC
    ");
    $stmt->execute([$office_id]);
    $counters = [];
    while ($row = $stmt->fetch()) {
        $serving = null;
        if ($row['ticket_id']) {
            $serving = [
                'id' => $row['ticket_id'],
                'queue_number' => $row['queue_number'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name']
            ];
        }
        $row['serving_ticket'] = $serving;
        $counters[] = $row;
    }

    // 2. Get Waiting Queue
    $stmt = $pdo->prepare("
        SELECT qt.id, qt.queue_number, qt.type, qt.priority, qt.joined_at, s.first_name, s.last_name, s.sr_code
        FROM queue_tickets qt
        JOIN students s ON s.id = qt.student_id
        WHERE qt.office_id = ? AND qt.status = 'waiting'
        ORDER BY qt.priority DESC, qt.joined_at ASC
    ");
    $stmt->execute([$office_id]);
    $waiting = $stmt->fetchAll();

    // 3. Get In-Progress/Called List (for the main table)
    $stmt = $pdo->prepare("
        SELECT qt.*, s.first_name, s.last_name, w.name as window_name
        FROM queue_tickets qt
        JOIN students s ON s.id = qt.student_id
        JOIN windows w ON w.id = qt.window_id
        WHERE qt.office_id = ? AND qt.status IN ('called', 'in_progress')
        ORDER BY qt.called_at DESC
    ");
    $stmt->execute([$office_id]);
    $in_progress = $stmt->fetchAll();

    json_response([
        'success' => true,
        'counters' => $counters,
        'waiting_queue' => $waiting,
        'in_progress_queue' => $in_progress
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error'], 500);
}