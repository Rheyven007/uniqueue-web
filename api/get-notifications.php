<?php
// api/get-notifications.php — AJAX: poll for new notifications

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_student_logged_in()) {
    json_response(['notifications' => []], 401);
}

$student_id = $_SESSION['student_id'];

try {
    // Fetch the 15 most recent notifications for the student
    $stmt = $pdo->prepare("
        SELECT n.*, qt.queue_number
        FROM notifications n
        JOIN queue_tickets qt ON n.ticket_id = qt.id
        WHERE n.student_id = ?
        ORDER BY n.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$student_id]);
    $rows = $stmt->fetchAll();

    $notifications = [];
    foreach ($rows as $row) {
        $message = '';
        
        // Generate user-friendly messages based on notification type
        if ($row['type'] === '3_away') {
            $message = "You are almost next! There are only 2 people ahead for Ticket #{$row['queue_number']}.";
        } elseif ($row['type'] === 'called') {
            $message = "Your Ticket #{$row['queue_number']} is being called! Please proceed to your window.";
        }

        $notifications[] = [
            'id'       => $row['id'],
            'type'     => $row['type'],
            'message'  => $message,
            'read_at'  => $row['read_at'],
            'time_ago' => time_ago_simple($row['created_at'])
        ];
    }

    json_response(['notifications' => $notifications]);

} catch (PDOException $e) {
    json_response(['error' => 'Database error'], 500);
}

/**
 * Simple helper for relative time
 */
function time_ago_simple(string $timestamp): string {
    $time_elapsed = time() - strtotime($timestamp);
    $minutes      = round($time_elapsed / 60);
    $hours        = round($time_elapsed / 3600);

    if ($time_elapsed < 60) return "Just now";
    if ($minutes < 60)      return "{$minutes}m ago";
    if ($hours < 24)        return "{$hours}h ago";
    
    return date('M d', strtotime($timestamp));
}