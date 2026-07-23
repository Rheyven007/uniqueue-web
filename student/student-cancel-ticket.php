<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_student();

$student_id = $_SESSION['student_id'];
$ticket_id  = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if ($ticket_id > 0) {

    // Only allow cancelling a ticket that actually belongs to this
    // student and hasn't already reached a terminal status — prevents
    // cancelling someone else's ticket via a guessed/edited URL, and
    // prevents re-cancelling something already done/cancelled.
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM queue_tickets
        WHERE id = ? AND student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$ticket_id, $student_id]);
    $ticket = $stmt->fetch();

    if ($ticket && !in_array($ticket['status'], ['done', 'cancelled', 'completed'], true)) {

        $update = $pdo->prepare("
            UPDATE queue_tickets
            SET status = 'cancelled'
            WHERE id = ? AND student_id = ?
        ");
        $update->execute([$ticket_id, $student_id]);

        header('Location: /student/dashboard.php?cancelled=1');
        exit;
    }
}

// Ticket missing, not owned by this student, or already in a
// terminal status — nothing to cancel, just bounce back.
header('Location: /student/dashboard.php');
exit;