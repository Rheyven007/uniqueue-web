<?php
// api/get-queue-status.php — Provides real-time queue status for a student's ticket

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // For get_speed_multiplier

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$ticket_id = get_param('ticket_id');
$student_id = $_SESSION['student_id'] ?? null;

if (empty($ticket_id) || empty($student_id)) {
    json_response(['success' => false, 'message' => 'Ticket ID or Student ID missing.'], 400);
}

try {
    // 1. Fetch the student's ticket details
    $stmt = $pdo->prepare("
        SELECT 
            qt.id, qt.queue_number, qt.type, qt.status, qt.priority, qt.office_id, qt.window_id, qt.joined_at,
            o.name AS office_name,
            w.name AS window_name, w.speed AS window_speed
        FROM queue_tickets qt
        JOIN offices o ON o.id = qt.office_id
        LEFT JOIN windows w ON w.id = qt.window_id
        WHERE qt.id = ? AND qt.student_id = ?
    ");
    $stmt->execute([$ticket_id, $student_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        json_response(['success' => false, 'message' => 'Ticket not found or does not belong to this student.'], 404);
    }

    $response_data = [
        'success'        => true,
        'id'             => (int)$ticket['id'],
        'queue_number'   => $ticket['queue_number'],
        'type'           => $ticket['type'],
        'status'         => $ticket['status'],
        'office_name'    => $ticket['office_name'],
        'window_name'    => $ticket['window_name'],
        'last_updated'   => date('Y-m-d H:i:s'),
        'people_ahead'   => 0,
        'ewt'            => 'N/A',
        'estimated_wait' => 'N/A'
    ];

    // Only calculate people ahead and EWT if the ticket is still waiting or called
    if (in_array($ticket['status'], ['waiting', 'called', 'in_progress'])) {
        // 2. Count people ahead
        // People ahead are those in 'waiting', 'called', 'in_progress' status,
        // who joined earlier or are higher priority, for the same office.
        $stmt_ahead = $pdo->prepare("
            SELECT COUNT(*)
            FROM queue_tickets
            WHERE office_id = ?
              AND status IN ('waiting', 'called', 'in_progress')
              AND (
                  (priority = 1 AND ? = 0) OR -- Higher priority tickets
                  (priority = ? AND joined_at < ?) OR -- Same priority, joined earlier
                  (priority = 1 AND ? = 1 AND joined_at < ?) -- Both priority, joined earlier
              )
              AND id != ?
        ");
        $stmt_ahead->execute([
            $ticket['office_id'],
            $ticket['priority'], // For (priority = 1 AND ? = 0)
            $ticket['priority'], $ticket['joined_at'], // For (priority = ? AND joined_at < ?)
            $ticket['priority'], $ticket['joined_at'], // For (priority = 1 AND ? = 1 AND joined_at < ?)
            $ticket['id']
        ]);
        $response_data['people_ahead'] = (int)$stmt_ahead->fetchColumn();

        // 3. Calculate Estimated Wait Time (EWT)
        // This is a simplified EWT. A more accurate one would consider individual document processing times
        // and window speeds for all tickets ahead.
        // For now, let's use an average processing time per ticket.
        // A more robust solution would involve summing up estimated_ticket_processing_time for all tickets ahead.
        
        // Fetch average processing time for documents in this office
        $avg_processing_time_stmt = $pdo->prepare("
            SELECT AVG(d.processing_time)
            FROM documents d
            WHERE d.office_id = ? AND d.processing_time IS NOT NULL
        ");
        $avg_processing_time_stmt->execute([$ticket['office_id']]);
        $average_doc_time = (float)$avg_processing_time_stmt->fetchColumn();

        // If no average time, use a default (e.g., 10 minutes per ticket)
        $average_ticket_processing_time = $average_doc_time > 0 ? $average_doc_time : 10;

        $speed_multiplier = get_speed_multiplier($ticket['window_speed'] ?? 'normal');
        $estimated_wait_time = round($response_data['people_ahead'] * $average_ticket_processing_time * $speed_multiplier);
        
        $response_data['ewt'] = $estimated_wait_time > 0 ? $estimated_wait_time : '< 1';
        $response_data['estimated_wait'] = $response_data['ewt'];    }

    json_response($response_data);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}