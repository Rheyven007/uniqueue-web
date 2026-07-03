<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_student();

$ticket_id = get_param('id');
$student_id = $_SESSION['student_id'];

// Fetch ticket and ensure it belongs to the logged-in student
$stmt = $pdo->prepare("
    SELECT qt.*, o.name AS office_name, o.description AS office_desc, w.name AS window_name
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    LEFT JOIN windows w ON w.id = qt.window_id
    WHERE qt.id = ? AND qt.student_id = ?
");
$stmt->execute([$ticket_id, $student_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('/student/dashboard.php');
}

// Fetch requested documents and their requirements
$stmt = $pdo->prepare("
    SELECT d.name, GROUP_CONCAT(dr.requirement SEPARATOR '||') as reqs
    FROM queue_ticket_document qtd
    JOIN documents d ON d.id = qtd.document_id
    LEFT JOIN document_requirements dr ON dr.document_id = d.id
    WHERE qtd.ticket_id = ?
    GROUP BY d.id
");
$stmt->execute([$ticket_id]);
$docs = $stmt->fetchAll();

$pageTitle = "My Queue Ticket";
include __DIR__ . '/../includes/header.php';
?>

<div class="container container--narrow">
    <div class="ticket-view">
        <div class="ticket-view__header">
            <span class="badge"><?= e(ucfirst($ticket['type'])) ?></span>
            <h1><?= e($ticket['office_name']) ?></h1>
        </div>

        <div class="ticket-view__number">
            <small>Queue Number</small>
            <strong><?= e($ticket['queue_number']) ?></strong>
        </div>

        <div class="ticket-view__details">
            <p><strong>Status:</strong> <?= ucfirst($ticket['status']) ?></p>
            <p><strong>Joined:</strong> <?= format_datetime($ticket['joined_at']) ?></p>
            <?php if ($ticket['appointment_date']): ?>
                <p><strong>Appointment Date:</strong> <?= e($ticket['appointment_date']) ?></p>
            <?php endif; ?>
            <?php if ($ticket['type'] === 'walkin'): ?>
                <p><strong>Counter:</strong> <?= e($ticket['window_name'] ?? 'To be assigned') ?></p>
            <?php else: ?>
                <p><strong>Counter:</strong> Unassigned (will be assigned on appointment day)</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($docs)): ?>
        <div class="ticket-view__requirements">
            <h3>Requirements to Prepare:</h3>
            <?php foreach ($docs as $doc): ?>
                <div class="doc-req-item">
                    <strong><?= e($doc['name']) ?></strong>
                    <ul>
                        <?php if ($doc['reqs']): ?>
                            <?php foreach (explode('||', $doc['reqs']) as $req): ?>
                                <li><?= e($req) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No specific requirements listed.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/queue-ticket.css">
<link rel="stylesheet" href="/assets/css/student.css">

<?php include __DIR__ . '/../includes/footer.php'; ?>