<?php
// auth/validate-srcode.php — AJAX: validate SR-Code against DB
// Returns JSON: { "valid": true/false, "message": "..." }

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['valid' => false, 'message' => 'Invalid request method.'], 405);
}

$sr_code = trim($_POST['sr_code'] ?? '');

if ($sr_code === '') {
    json_response(['valid' => false, 'message' => 'SR-Code is required.']);
}

// Basic format check: two digits, hyphen, five digits (e.g. 22-12345)
if (!preg_match('/^\d{2}-\d{5}$/', $sr_code)) {
    json_response(['valid' => false, 'message' => 'SR-Code must be in YY-NNNNN format (e.g. 22-12345).']);
}

$stmt = $pdo->prepare("SELECT id FROM students WHERE sr_code = ? LIMIT 1");
$stmt->execute([$sr_code]);
$exists = (bool)$stmt->fetch();

if ($exists) {
    json_response(['valid' => true, 'message' => 'SR-Code found.']);
} else {
    json_response(['valid' => false, 'message' => 'SR-Code not found. Please check and try again.']);
}
