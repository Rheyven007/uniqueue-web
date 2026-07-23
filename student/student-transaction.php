<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_student();

$student_id = $_SESSION['student_id'];

/* ── Filters ─────────────────────────────────────────────────── */
$allowedStatuses = ['all', 'waiting', 'called', 'in_progress', 'done', 'cancelled'];
$status = $_GET['status'] ?? 'all';
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$search = trim($_GET['q'] ?? '');

$where  = ['qt.student_id = ?'];
$params = [$student_id];

if ($status !== 'all') {
    $where[] = 'qt.status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = '(qt.queue_number LIKE ? OR o.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = implode(' AND ', $where);

/* ── Pagination ──────────────────────────────────────────────── */
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM queue_tickets qt
    JOIN offices o ON o.id = qt.office_id
    WHERE $whereSql
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

/* ── Transactions (filtered + paginated) ────────────────────── */
$sql = "
    SELECT
        qt.id,
        qt.queue_number,
        qt.type,
        qt.status,
        qt.priority,
        qt.joined_at,
        qt.called_at,
        qt.done_at,
        qt.created_at,
        o.name AS office_name,
        w.name AS window_name,
        fb.id     AS feedback_id,
        fb.rating AS feedback_rating,
        fb.comment AS feedback_comment,
        GROUP_CONCAT(
            CONCAT(d.name, IF(qtd.quantity > 1, CONCAT(' (x', qtd.quantity, ')'), ''))
            ORDER BY d.name
            SEPARATOR ', '
        ) AS documents
    FROM queue_tickets qt
    JOIN offices o             ON o.id  = qt.office_id
    LEFT JOIN windows w        ON w.id  = qt.window_id
    LEFT JOIN queue_ticket_document qtd ON qtd.ticket_id = qt.id
    LEFT JOIN documents d      ON d.id  = qtd.document_id
    LEFT JOIN feedbacks fb     ON fb.ticket_id = qt.id
    WHERE $whereSql
    GROUP BY qt.id
    ORDER BY qt.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

/* ── Overall stats (unfiltered, for the summary row) ────────── */
$statsStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM queue_tickets
    WHERE student_id = ?
    GROUP BY status
");
$statsStmt->execute([$student_id]);
$statsRaw = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalAll       = array_sum($statsRaw);
$doneCount      = (int)($statsRaw['done'] ?? 0);
$cancelledCount = (int)($statsRaw['cancelled'] ?? 0);
$activeCount    = (int)($statsRaw['waiting'] ?? 0)
                + (int)($statsRaw['called'] ?? 0)
                + (int)($statsRaw['in_progress'] ?? 0);

/* Helper to keep existing query params when building filter/page links */
function txn_query_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 'all') {
            unset($params[$key]);
        }
    }
    $qs = http_build_query($params);
    return '/student/student-transaction.php' . ($qs ? "?$qs" : '');
}

/* Build the read-only payload shown in the transaction detail modal.
   Dates are pre-formatted here so the modal never re-derives or edits
   any value — it only ever displays what the server already rendered. */
function txn_modal_payload(array $txn): array {
    return [
        'queue_number'   => $txn['queue_number'],
        'type'           => $txn['type'] === 'appointment' ? 'Appointment' : 'Walk-in',
        'priority'       => (int)$txn['priority'] === 1,
        'status'         => ucfirst(str_replace('_', ' ', $txn['status'])),
        'status_raw'     => $txn['status'],
        'office_name'    => $txn['office_name'],
        'window_name'    => $txn['window_name'] ?: null,
        'documents'      => $txn['documents'] ?: null,
        'joined_at'      => date('M j, Y g:i A', strtotime($txn['joined_at'] ?? $txn['created_at'])),
        'called_at'      => $txn['called_at'] ? date('M j, Y g:i A', strtotime($txn['called_at'])) : null,
        'done_at'        => $txn['done_at'] ? date('M j, Y g:i A', strtotime($txn['done_at'])) : null,
        'feedback_rating'=> $txn['feedback_id'] ? (int)$txn['feedback_rating'] : null,
        'feedback_comment' => $txn['feedback_id'] ? ($txn['feedback_comment'] ?: null) : null,
    ];
}

$statusLabels = [
    'all'         => 'All',
    'waiting'     => 'Waiting',
    'called'      => 'Called',
    'in_progress' => 'In Progress',
    'done'        => 'Done',
    'cancelled'   => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uniqueue &mdash; Transaction History</title>
    <link rel="stylesheet" href="/assets/css/student-dashboard.css">
    <link rel="stylesheet" href="/assets/css/queue-status.css">
    <link rel="stylesheet" href="/assets/css/student-transaction.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/footer.css">
</head>
<body class="dashboard-body">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="dashboard-main">

    <!-- ── BANNER ───────────────────────────────────── -->
    <section class="dash-hero">
        <div class="dash-hero__left">
            <div class="dash-hero__badge">
                <span class="dash-hero__badge-dot"></span>
                Transaction History
            </div>
            <div class="dash-hero__name">Your Queue Transactions</div>
            <div class="dash-hero__code"><?= e($_SESSION['sr_code']) ?></div>
        </div>

        <div class="dash-hero__stats">
            <div class="hero-stat">
                <span class="hero-stat__value"><?= $totalAll ?></span>
                <span class="hero-stat__label">Total</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__value"><?= $activeCount ?></span>
                <span class="hero-stat__label">Active</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__value"><?= $doneCount ?></span>
                <span class="hero-stat__label">Done</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__value"><?= $cancelledCount ?></span>
                <span class="hero-stat__label">Cancelled</span>
            </div>
        </div>
    </section>

    <!-- ── FILTER BAR ───────────────────────────────── -->
    <section class="panel-card">
        <div class="panel-card__body">
            <form method="get" action="/student/student-transaction.php" class="txn-filter-bar">

                <div class="txn-filter-bar__search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        type="text"
                        name="q"
                        value="<?= e($search) ?>"
                        placeholder="Search queue number or office&hellip;"
                        class="txn-filter-bar__input">
                </div>

                <div class="txn-filter-bar__status">
                    <select name="status" class="txn-filter-bar__select">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn--primary btn--sm">Filter</button>

                <?php if ($status !== 'all' || $search !== ''): ?>
                    <a href="/student/student-transaction.php" class="btn btn--ghost btn--sm">Clear</a>
                <?php endif; ?>

            </form>
        </div>
    </section>

    <!-- ── TRANSACTION LIST ────────────────────────────── -->
    <section class="panel-card">
        <div class="panel-card__header">
            <div class="panel-card__title">
                Transactions
                <?php if ($status !== 'all' || $search !== ''): ?>
                    <span class="panel-card__count"><?= $totalRows ?> Result<?= $totalRows === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </div>
            <span class="panel-card__live">
                <span class="panel-card__live-dot"></span>
                Live
            </span>
        </div>

        <div class="panel-card__body">
            <?php if ($transactions): ?>
                <div class="txn-list" id="txnList">
                    <?php foreach ($transactions as $txn): ?>
                        <div class="transaction-card transaction-card--clickable"
                             role="button"
                             tabindex="0"
                             aria-haspopup="dialog"
                             data-txn="<?= e(json_encode(txn_modal_payload($txn))) ?>">

                            <div class="transaction-card__top">
                                <div class="transaction-card__id">
                                    <span class="transaction-card__number">#<?= e($txn['queue_number']) ?></span>
                                    <span class="ticket-type-badge ticket-type-badge--<?= e($txn['type']) ?>">
                                        <?= $txn['type'] === 'appointment' ? 'Appointment' : 'Walk-in' ?>
                                    </span>
                                    <?php if ((int)$txn['priority'] === 1): ?>
                                        <span class="ticket-type-badge ticket-type-badge--priority">Priority</span>
                                    <?php endif; ?>
                                </div>
                                <span class="ticket-status-badge ticket-status-badge--<?= e($txn['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $txn['status'])) ?>
                                </span>
                            </div>

                            <div class="transaction-card__body">
                                <div class="transaction-card__row">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                    <span><?= e($txn['office_name']) ?></span>
                                    <?php if ($txn['window_name']): ?>
                                        <span class="transaction-card__dim">&middot; <?= e($txn['window_name']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($txn['documents']): ?>
                                    <div class="transaction-card__row">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span><?= e($txn['documents']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="transaction-card__dates">
                                    <span>Joined: <?= date('M j, Y g:i A', strtotime($txn['joined_at'] ?? $txn['created_at'])) ?></span>
                                    <?php if ($txn['done_at']): ?>
                                        <span>Done: <?= date('M j, Y g:i A', strtotime($txn['done_at'])) ?></span>
                                    <?php elseif ($txn['status'] === 'cancelled'): ?>
                                        <span>Cancelled</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="transaction-card__footer">
                                <?php if (in_array($txn['status'], ['waiting', 'called', 'in_progress'], true)): ?>
                                    <a href="/student/student-dashboard.php" class="btn btn--outline btn--xs" data-no-modal>
                                        Track Queue
                                    </a>
                                <?php elseif ($txn['status'] === 'done'): ?>
                                    <?php if ($txn['feedback_id']): ?>
                                        <span class="transaction-card__rating" title="<?= (int)$txn['feedback_rating'] ?> out of 5">
                                            <?= str_repeat('&#9733;', (int)$txn['feedback_rating']) ?><?= str_repeat('&#9734;', 5 - (int)$txn['feedback_rating']) ?>
                                            Rated
                                        </span>
                                    <?php else: ?>
                                        <a href="/student/feedback.php?ticket_id=<?= (int)$txn['id'] ?>" class="btn btn--outline btn--xs" data-no-modal>
                                            Rate Experience
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="txn-pagination">
                        <a href="<?= e(txn_query_url(['page' => max(1, $page - 1)])) ?>"
                           class="txn-pagination__link <?= $page <= 1 ? 'txn-pagination__link--disabled' : '' ?>">
                            &larr; Prev
                        </a>
                        <span class="txn-pagination__current">Page <?= $page ?> of <?= $totalPages ?></span>
                        <a href="<?= e(txn_query_url(['page' => min($totalPages, $page + 1)])) ?>"
                           class="txn-pagination__link <?= $page >= $totalPages ? 'txn-pagination__link--disabled' : '' ?>">
                            Next &rarr;
                        </a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="txn-empty">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="8" y1="13" x2="16" y2="13"/>
                        <line x1="8" y1="17" x2="12" y2="17"/>
                    </svg>
                    <?php if ($status !== 'all' || $search !== ''): ?>
                        <span>No transactions match your filter.</span>
                        <a href="/student/student-transaction.php" class="btn btn--outline btn--sm">Clear Filters</a>
                    <?php else: ?>
                        <span>You don't have any queue transactions yet.</span>
                        <a href="/student/student-dashboard.php" class="btn btn--outline btn--sm">Join a Queue</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- ── TRANSACTION DETAIL MODAL (read-only) ──────────────────── -->
<div class="txn-modal" id="txnModal" hidden aria-hidden="true">
    <div class="txn-modal__overlay" data-txn-close></div>
    <div class="txn-modal__panel" role="dialog" aria-modal="true" aria-labelledby="txnModalTitle">
        <button type="button" class="txn-modal__close" data-txn-close aria-label="Close">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <div class="txn-modal__banner">
            <span class="txn-modal__eyebrow">Transaction Details</span>
            <div class="txn-modal__banner-row">
                <span class="txn-modal__number" id="txnModalTitle"></span>
                <span class="ticket-status-badge" id="txnModalStatus"></span>
            </div>
            <div class="txn-modal__badges" id="txnModalBadges"></div>
        </div>

        <div class="txn-modal__body">

            <div class="txn-modal__section">
                <h3 class="txn-modal__section-title">Details</h3>
                <dl class="txn-modal__details">
                    <div class="txn-modal__field">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <div>
                            <dt>Office</dt>
                            <dd id="txnModalOffice"></dd>
                        </div>
                    </div>
                    <div class="txn-modal__field" id="txnModalWindowRow" hidden>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="3" width="16" height="18" rx="2"/>
                            <line x1="9" y1="8" x2="15" y2="8"/>
                        </svg>
                        <div>
                            <dt>Window</dt>
                            <dd id="txnModalWindow"></dd>
                        </div>
                    </div>
                    <div class="txn-modal__field" id="txnModalDocsRow" hidden>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <div>
                            <dt>Documents</dt>
                            <dd id="txnModalDocs"></dd>
                        </div>
                    </div>
                </dl>
            </div>

            <div class="txn-modal__section">
                <h3 class="txn-modal__section-title">Timeline</h3>
                <ul class="txn-modal__timeline">
                    <li class="txn-modal__timeline-item txn-modal__timeline-item--done">
                        <span class="txn-modal__dot"></span>
                        <div>
                            <dt>Joined</dt>
                            <dd id="txnModalJoined"></dd>
                        </div>
                    </li>
                    <li class="txn-modal__timeline-item txn-modal__timeline-item--done" id="txnModalCalledRow" hidden>
                        <span class="txn-modal__dot"></span>
                        <div>
                            <dt>Called</dt>
                            <dd id="txnModalCalled"></dd>
                        </div>
                    </li>
                    <li class="txn-modal__timeline-item txn-modal__timeline-item--done" id="txnModalDoneRow" hidden>
                        <span class="txn-modal__dot"></span>
                        <div>
                            <dt id="txnModalDoneLabel">Done</dt>
                            <dd id="txnModalDone"></dd>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="txn-modal__section txn-modal__section--feedback" id="txnModalFeedbackSection" hidden>
                <h3 class="txn-modal__section-title">Feedback</h3>
                <div class="txn-modal__feedback-card">
                    <span class="txn-modal__feedback-stars" id="txnModalRating"></span>
                    <p class="txn-modal__feedback-comment" id="txnModalComment" hidden></p>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/student-transaction.js"></script>
</body>
</html>