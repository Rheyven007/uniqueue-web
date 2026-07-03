<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$doc_id = $_GET['doc_id'] ?? null;

if (!$doc_id) {
    echo "<p>No document selected.</p>";
    exit;
}

/*
    Get requirements for selected document
*/
$stmt = $pdo->prepare("
    SELECT id, requirement
    FROM document_requirements
    WHERE document_id = ?
");
$stmt->execute([$doc_id]);
$requirements = $stmt->fetchAll();

if (!$requirements) {
    echo "<p>No requirements for this document.</p>";
    exit;
}

/*
    Render checklist (IMPORTANT for JS validation)
*/
foreach ($requirements as $r) {
    echo "
        <label style='display:block;margin:5px 0;'>
            <input type='checkbox' class='req-check'>
            " . htmlspecialchars($r['requirement']) . "
        </label>
    ";
}