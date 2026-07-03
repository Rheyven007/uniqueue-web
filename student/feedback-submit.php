<?php
// student/feedback-submit.php — Feedback form page

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_student();

$ticket_id = get_param('ticket_id');
$student_id = $_SESSION['student_id'];
$error = '';

if (!$ticket_id) {
    redirect('/student/dashboard.php');
}

// Verify ticket belongs to student, is 'done', and has no existing feedback
$stmt = $pdo->prepare("
    SELECT qt.id, qt.queue_number, o.name AS office_name
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    LEFT JOIN feedbacks f ON f.ticket_id = qt.id
    WHERE qt.id = ? AND qt.student_id = ? AND qt.status = 'done' AND f.id IS NULL
");
$stmt->execute([$ticket_id, $student_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('/student/dashboard.php'); // Ticket not found, not done, or already has feedback
}

$pageTitle = "Submit Feedback";
include __DIR__ . '/../includes/header.php';
?>

<div class="container container--narrow">
    <div class="form-card">
        <h1 class="form-card__title">How was your experience?</h1>
        <p class="form-card__sub">Please rate your recent transaction at <strong><?= e($ticket['office_name']) ?></strong> (Ticket #<?= e($ticket['queue_number']) ?>).</p>

        <?php if ($error): ?>
            <div class="alert alert--danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form id="feedback-form" method="POST">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket_id ?>">
            
            <div class="form-group">
                <label for="rating">Overall Satisfaction</label>
                <div class="rating-stars" id="rating-stars">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
                <input type="hidden" name="rating" id="rating" required>
                <div id="rating-error" class="form-error"></div>
            </div>

            <div class="form-group">
                <label for="comment">Comments (Optional)</label>
                <textarea name="comment" id="comment" class="form-control" rows="4" placeholder="Tell us more about your experience..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary btn--block">Submit Feedback</button>
                <a href="/student/dashboard.php" class="btn btn--ghost btn--block">Skip for now</a>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/feedback.js"></script>
<link rel="stylesheet" href="/assets/css/feedback.css">

<?php include __DIR__ . '/../includes/footer.php'; ?>