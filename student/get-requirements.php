<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Accepts doc_ids=1,2,3 (comma-separated). doc_id (singular) is still
// accepted for backward compatibility with any old callers.
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

/* Document names, in the order they were requested */
$doc_stmt = $pdo->prepare("SELECT id, name FROM documents WHERE id IN ($placeholders)");
$doc_stmt->execute($doc_ids);
$doc_names = [];
foreach ($doc_stmt->fetchAll() as $d) {
    $doc_names[$d['id']] = $d['name'];
}

/* Requirements for all requested documents in one query, grouped in PHP */
$req_stmt = $pdo->prepare("
    SELECT document_id, id, requirement
    FROM document_requirements
    WHERE document_id IN ($placeholders)
    ORDER BY document_id, id
");
$req_stmt->execute($doc_ids);

$grouped = [];
foreach ($req_stmt->fetchAll() as $r) {
    $grouped[$r['document_id']][] = $r;
}

/* Render one group per document, in the order the student selected them,
   so requirements from multiple documents stack instead of overwriting
   each other. req-check class is required by the wizard's JS validation. */
foreach ($doc_ids as $did) {
    if (!isset($doc_names[$did])) continue; // skip anything invalid/unrecognized

    echo "<div class='req-group'>";
    echo "<div class='req-group__title'>" . htmlspecialchars($doc_names[$did]) . "</div>";

    if (empty($grouped[$did])) {
        echo "<p style='margin:4px 0 10px;font-size:.85rem;color:var(--ink-light);'>No specific requirements listed for this document.</p>";
    } else {
        foreach ($grouped[$did] as $r) {
            echo "
                <label style='display:block;margin:5px 0;'>
                    <input type='checkbox' class='req-check' data-doc-id='" . (int)$did . "'>
                    " . htmlspecialchars($r['requirement']) . "
                </label>
            ";
        }
    }

    echo "</div>";
}