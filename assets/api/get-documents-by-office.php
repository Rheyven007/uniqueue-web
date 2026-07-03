<?php
// api/get-documents-by-office.php — Fetches documents for a given office

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$office_id = get_param('office_id');

if (empty($office_id)) {
    json_response(['success' => false, 'message' => 'Office ID is required.'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, GROUP_CONCAT(dr.requirement SEPARATOR '||') as requirements
        FROM documents d
        LEFT JOIN document_requirements dr ON dr.document_id = d.id
        WHERE d.office_id = ?
        GROUP BY d.id
        ORDER BY d.name ASC");
    $stmt->execute([$office_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'success' => true,
        'documents' => $documents
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}