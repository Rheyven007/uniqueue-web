<?php
// student/get-ticket-status.php — Polled by queue.js (pollTicketStatus)

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_student();

header('Content-Type: application/json');

$ticket_id  = get_param('ticket_id');
$student_id = $_SESSION['student_id'] ?? null;

if (empty($ticket_id) || empty($student_id)) {
    json_response(['success' => false, 'message' => 'Ticket ID or Student ID missing.'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT qt.id, qt.queue_number, qt.type, qt.status, qt.window_id,
               w.name AS window_name
        FROM queue_tickets qt
        LEFT JOIN windows w ON w.id = qt.window_id
        WHERE qt.id = ? AND qt.student_id = ?
    ");
    $stmt->execute([$ticket_id, $student_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        json_response(['success' => false, 'message' => 'Ticket not found.'], 404);
    }

    json_response([
        'success' => true,
        'ticket'  => $ticket
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}