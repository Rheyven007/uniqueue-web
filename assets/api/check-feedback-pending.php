<?php
// api/check-feedback-pending.php — AJAX: check if student has pending feedback

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_student_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$student_id = $_SESSION['student_id'];

try {
    $stmt = $pdo->prepare("
        SELECT qt.id, qt.queue_number, o.name AS office_name
        FROM queue_tickets qt
        JOIN offices o ON o.id = qt.office_id
        LEFT JOIN feedbacks f ON f.ticket_id = qt.id
        WHERE qt.student_id = ? AND qt.status = 'done' AND f.id IS NULL
        ORDER BY qt.done_at DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $pending_feedback = $stmt->fetch();

    json_response(['success' => true, 'pending' => (bool)$pending_feedback, 'ticket' => $pending_feedback]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error'], 500);
}