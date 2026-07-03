<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_admin();

if (!is_post()) {
    json_response(['success' => false, 'message' => 'Invalid method'], 405);
}

$ticket_id = post('ticket_id');
$action = post('action');

if (!$ticket_id || !$action) {
    json_response(['success' => false, 'message' => 'Missing data'], 400);
}

try {
    $stmt = null;
    if ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status = 'done', done_at = NOW() WHERE id = ?");
    } elseif ($action === 'skip') {
        // Reset status to waiting but KEEP window_id — the student's
        // designated counter (assigned on join) should not change.
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status = 'waiting', called_at = NULL WHERE id = ?");
    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status = 'cancelled', done_at = NOW() WHERE id = ?");
    } else {
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
    }

    if ($stmt->execute([$ticket_id])) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => 'Update failed'], 500);
    }
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error'], 500);
}