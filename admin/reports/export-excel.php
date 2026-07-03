<?php
// admin/reports/export-excel.php — Export transaction report to CSV (Excel compatible)

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$start_date = get_param('start_date', get_param('date', date('Y-m-d')));
$end_date   = get_param('end_date', $start_date);
$office_id = get_param('office_id');

// Fetch data
$sql = "SELECT qt.queue_number, o.name as office_name, s.sr_code, 
               s.first_name, s.last_name, qt.type, qt.status, qt.joined_at
        FROM queue_tickets qt
        JOIN offices o ON o.id = qt.office_id
        JOIN students s ON s.id = qt.student_id
        WHERE DATE(qt.joined_at) BETWEEN ? AND ?";

$params = [$start_date, $end_date];
if ($office_id) {
    $sql .= " AND qt.office_id = ?";
    $params[] = (int)$office_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=uniqueue_report_'.$start_date.'_to_'.$end_date.'.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Queue Number', 'Office', 'SR-Code', 'First Name', 'Last Name', 'Type', 'Status', 'Joined At']);

// Loop over the rows and output them
foreach ($rows as $row) {
    fputcsv($output, [
        $row['queue_number'], $row['office_name'], $row['sr_code'],
        $row['first_name'], $row['last_name'], $row['type'],
        $row['status'], $row['joined_at']
    ]);
}

fclose($output);