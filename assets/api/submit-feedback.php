<?php
// api/submit-feedback.php — AJAX: submit feedback form

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_student_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (!is_post()) {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

$student_id = $_SESSION['student_id'];
$ticket_id = post('ticket_id');
$rating = (int)post('rating');
$comment = post('comment');

if (!$ticket_id || $rating < 1 || $rating > 5) {
    json_response(['success' => false, 'message' => 'Invalid ticket or rating provided'], 400);
}

try {
    // Ensure the ticket belongs to the student and is 'done' and has no existing feedback
    $stmt = $pdo->prepare("SELECT qt.id FROM queue_tickets qt LEFT JOIN feedbacks f ON f.ticket_id = qt.id WHERE qt.id = ? AND qt.student_id = ? AND qt.status = 'done' AND f.id IS NULL");
    $stmt->execute([$ticket_id, $student_id]);
    if (!$stmt->fetch()) {
        json_response(['success' => false, 'message' => 'Ticket not eligible for feedback or already submitted'], 403);
    }

    $stmt = $pdo->prepare("INSERT INTO feedbacks (ticket_id, student_id, rating, comment) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$ticket_id, $student_id, $rating, $comment])) {
        json_response(['success' => true, 'message' => 'Feedback submitted successfully']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save feedback'], 500);
    }
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error'], 500);
}