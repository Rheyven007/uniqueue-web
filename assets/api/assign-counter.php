<?php
// api/assign-counter.php — Smart counter assignment logic

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // For get_speed_multiplier

// Ensure only admins/staff can call the next student
require_admin();

header('Content-Type: application/json');

if (!is_post()) {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$window_id = post('window_id');

if (empty($window_id)) {
    json_response(['success' => false, 'message' => 'Window ID is required.'], 400);
}

try {
    // 1. Get window info to know which office it belongs to
    $stmt = $pdo->prepare("SELECT * FROM windows WHERE id = ?");
    $stmt->execute([$window_id]);
    $window = $stmt->fetch();

    if (!$window) {
        json_response(['success' => false, 'message' => 'Window not found.'], 404);
    }

    $window_speed_multiplier = get_speed_multiplier($window['speed']);

    // 2. Get document IDs this window is authorized to handle
    $stmt = $pdo->prepare("SELECT document_id FROM window_document WHERE window_id = ?");
    $stmt->execute([$window_id]);
    $allowed_docs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allowed_docs)) {
        json_response(['success' => false, 'message' => 'This window is not configured to handle any documents. Please assign documents to it.'], 400);
    }

    // 3. Find the next eligible ticket for this window, prioritizing by estimated wait time.
    // This involves calculating the estimated processing time for each potential ticket.
    $placeholders = implode(',', array_fill(0, count($allowed_docs), '?'));
    
    // Subquery to calculate estimated processing time for each ticket
    // We assume documents.processing_time is in minutes.
    $sql = "
        SELECT 
            qt.*, 
            s.first_name, 
            s.last_name, 
            s.sr_code,
            (
                SELECT SUM(d.processing_time * qtd.quantity)
                FROM queue_ticket_document qtd
                JOIN documents d ON d.id = qtd.document_id
                WHERE qtd.ticket_id = qt.id
            ) AS estimated_ticket_processing_time
            FROM queue_tickets qt
            JOIN students s ON qt.student_id = s.id
            WHERE qt.office_id = ? 
              AND qt.status = 'waiting'
              AND NOT EXISTS (
                  SELECT 1 FROM queue_ticket_document qtd 
                  WHERE qtd.ticket_id = qt.id AND qtd.document_id NOT IN ($placeholders)
              )
            ORDER BY 
                (qt.window_id = ?) DESC, -- prefer students already designated to this counter
                qt.priority DESC, 
                (estimated_ticket_processing_time * ?) ASC, -- Factor in window speed
                qt.joined_at ASC
            LIMIT 1";

    // Parameters for the main query: office_id, allowed_docs, window_id, window_speed_multiplier
    $params = array_merge([$window['office_id']], $allowed_docs, [$window_id, $window_speed_multiplier]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        json_response(['success' => false, 'message' => 'No eligible students in the queue for this window. Either no one is waiting, or no one has documents this window can handle.'], 200);
    }

    // 4. Update ticket: Assign to window and change status to 'called'
    // Also update status to 'in_progress' immediately if the window is ready to serve.
    // For simplicity, let's keep it 'called' and let the admin manually change to 'in_progress'.
    // Or, if we want to be smart, we can check if the window is currently serving.
    // For now, stick to 'called' as per existing code.
    $updateStmt = $pdo->prepare("UPDATE queue_tickets SET window_id = ?, status = 'called', called_at = NOW(), updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$window_id, $ticket['id']]); // Add updated_at for consistency

    json_response([
        'success' => true,
        'ticket' => $ticket
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}