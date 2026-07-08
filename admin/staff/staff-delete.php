<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id = $_SESSION['office_id'] ?? null;

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: staff-list.php");
    exit;
}

// Make sure the staff belongs to this office
$stmt = $pdo->prepare("
    SELECT id
    FROM staff
    WHERE id = ?
    AND office_id = ?
    LIMIT 1
");
$stmt->execute([$id, $office_id]);

if (!$stmt->fetch()) {
    header("Location: staff-list.php");
    exit;
}

// Soft delete
$update = $pdo->prepare("
    UPDATE staff
    SET
        status = 'inactive',
        updated_at = NOW()
    WHERE id = ?
");

$update->execute([$id]);

header("Location: staff-list.php");
exit;