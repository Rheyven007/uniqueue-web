<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$raw = $_GET['doc_ids'] ?? $_GET['doc_id'] ?? null;

if (!$raw) {
    echo "<p>No document selected.</p>";
    exit;
}

$doc_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));

if (!$doc_ids) {
    echo "<p>No document selected.</p>";
    exit;
}

$placeholders = implode(',', array_fill(0, count($doc_ids), '?'));

$req_stmt = $pdo->prepare("
    SELECT requirement
    FROM document_requirements
    WHERE document_id IN ($placeholders)
    ORDER BY id
");
$req_stmt->execute($doc_ids);

/* Remove duplicate requirements */
$uniqueRequirements = [];

foreach ($req_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $key = mb_strtolower(trim($row['requirement']));

    if (!isset($uniqueRequirements[$key])) {
        $uniqueRequirements[$key] = $row['requirement'];
    }
}

echo "<div class='req-group'>";
echo "<div class='req-group__title'>Requirements</div>";

if (empty($uniqueRequirements)) {

    echo "<p style='margin:4px 0 10px;font-size:.85rem;color:var(--ink-light);'>
            No specific requirements listed.
          </p>";

} else {

    foreach ($uniqueRequirements as $requirement) {

        echo "
            <label style='display:block;margin:5px 0;'>
                <input type='checkbox' class='req-check'>
                " . htmlspecialchars($requirement) . "
            </label>
        ";
    }
}

echo "</div>";