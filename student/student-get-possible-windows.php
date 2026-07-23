<?php

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/algo.php';
require_student();

header('Content-Type: application/json');

$office_id = (int)($_GET['office_id'] ?? 0);
$raw       = $_GET['doc_ids'] ?? '';
$type      = $_GET['type'] ?? 'walkin';
$type      = in_array($type, ['walkin', 'appointment'], true) ? $type : 'walkin';

$raw_doc_ids = [];
if (is_array($raw)) {
    $raw_doc_ids = $raw;
} elseif (is_string($raw)) {
    $raw_doc_ids = explode(',', $raw);
}
$document_ids = array_values(array_unique(array_filter(array_map('intval', $raw_doc_ids))));

if (!$office_id) {
    json_response([
        'success' => true,
        'windows' => []
    ]);
}

if ($type === 'appointment') {

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM windows
        WHERE office_id = ?
        AND queue_type IN ('appointment','both')
        AND status = 'open'
        ORDER BY name
    ");
    $stmt->execute([$office_id]);
    $windows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {

    if (!$document_ids) {
        json_response([
            'success' => true,
            'windows' => []
        ]);
    }
    $windows = get_eligible_windows(
        $pdo,
        $office_id,
        $document_ids,
        'walkin'
    );
}

json_response([
    'success' => true,
    'windows' => array_map(fn($w)=>[
        'id'=>(int)$w['id'],
        'name'=>$w['name']
    ],$windows)
]);
