<?php
// student/get-possible-windows.php — Polled by queue.js while picking documents.
// Shows the student which windows COULD serve their combination of
// documents. The actual assignment still happens server-side, in
// pick_best_window(), at ticket submission — this is display-only.

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/algo.php';
require_student();

header('Content-Type: application/json');

$office_id = (int)($_GET['office_id'] ?? 0);
$raw       = $_GET['doc_ids'] ?? '';
$type      = $_GET['type'] ?? 'walkin';
$type      = in_array($type, ['walkin', 'appointment'], true) ? $type : 'walkin';
$document_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));

if (!$office_id || !$document_ids) {
    json_response(['success' => false, 'windows' => []]);
}

$windows = get_eligible_windows($pdo, $office_id, $document_ids, $type);

json_response([
    'success' => true,
    'windows' => array_map(fn($w) => ['id' => (int)$w['id'], 'name' => $w['name']], $windows),
]);
