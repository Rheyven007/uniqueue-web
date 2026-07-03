<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_student();

$ticket_id = get_param('ticket_id');
$student_id = $_SESSION['student_id'];

if (!$ticket_id) {
    redirect('/student/dashboard.php');
}

// Ensure the student only monitors their own ticket
$stmt = $pdo->prepare("SELECT id FROM queue_tickets WHERE id = ? AND student_id = ?");
$stmt->execute([$ticket_id, $student_id]);
if (!$stmt->fetch()) {
    redirect('/student/dashboard.php');
}

$pageTitle = "Real-Time Queue Status";
include __DIR__ . '/../includes/header.php';
?>

<div class="container container--narrow">
    <div class="status-card shadow-lg" id="queue-status-container" data-ticket-id="<?= e($ticket_id) ?>">
        <div class="status-card__header">
            <h1 id="office-name">Loading Office...</h1>
            <div id="status-badge" class="ticket-status-badge">...</div>
        </div>

        <div class="status-card__main">
            <div class="queue-number-display">
                <small>Your Ticket Number</small>
                <strong id="queue-number">...</strong>
            </div>

            <div id="waiting-info" class="waiting-info">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">People Ahead</span>
                        <span class="info-value" id="people-ahead">...</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estimated Wait</span>
                        <span class="info-value"><span id="ewt">...</span> mins</span>
                    </div>
                </div>
                <div class="info-item" style="margin-top: 1rem;">
                    <span class="info-label">Assigned Counter</span>
                    <span class="info-value" id="assigned-window-name" style="font-size: 1.3rem;">...</span>
                </div>
            </div>

            <div id="called-info" class="called-info hidden">
                <div class="called-alert">
                    <div class="called-alert__icon">🔔</div>
                    <h2>Please proceed now!</h2>
                    <p>Go to your assigned service window:</p>
                    <div class="window-name" id="window-name">...</div>
                </div>
            </div>
        </div>

        <div class="status-card__footer">
            <p class="text-muted">Last updated: <span id="last-updated">...</span></p>
            <div class="status-card__actions">
                <button onclick="window.location.reload()" class="btn btn--outline btn--sm">Refresh Now</button>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<link rel="stylesheet" href="/assets/css/queue-status.css">
<script src="/assets/js/queue-monitor.js"></script>
<link rel="stylesheet" href="student.css">

<?php include __DIR__ . '/../includes/footer.php'; ?>