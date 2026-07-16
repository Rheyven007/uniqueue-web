<?php
// api/get-dashboard-stats.php — Provides real-time stats for the student dashboard.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Ensure timestamps are handled in the correct local timezone.
date_default_timezone_set('Asia/Manila');

try {
    $total_waiting = (int)$pdo->query("
        SELECT COUNT(*) FROM queue_tickets
        WHERE status = 'waiting' AND DATE(created_at) = CURDATE()
    ")->fetchColumn();

    $done_today = (int)$pdo->query("
        SELECT COUNT(*) FROM queue_tickets
        WHERE status IN ('done', 'completed') AND DATE(created_at) = CURDATE()
    ")->fetchColumn();

    $hours_elapsed = max(1, (int)date('H') - 8); // Assume office opens at 8am
    $avg_per_hour  = $done_today > 0 ? round($done_today / $hours_elapsed) : 6; // Default to 6 if no data
    $est_wait_mins = $avg_per_hour > 0 ? round(($total_waiting / $avg_per_hour) * 60) : null;

    json_response([
        'success'       => true,
        'total_waiting' => $total_waiting,
        'est_wait_mins' => $est_wait_mins,
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}