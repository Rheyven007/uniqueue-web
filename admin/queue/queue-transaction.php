<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

$staffId = $_SESSION['staff_id'];

/* ---------------------------------------------------------
   Current staff + their assigned window
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        s.name,
        s.window_id,
        w.name AS window_name,
        w.queue_type,
        o.name AS office_name

    FROM staff s
    LEFT JOIN windows w ON s.window_id = w.id
    LEFT JOIN offices o ON s.office_id = o.id
    WHERE s.id = ?
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$staffName  = $staff['name'] ?? 'Staff';
$windowId   = $staff['window_id'] ?? null;
$windowName = $staff['window_name'] ?? 'No Window Assigned';
$officeName = $staff['office_name'] ?? '';

/* ---------------------------------------------------------
   Filters (GET)
--------------------------------------------------------- */
$allowedStatus = ['waiting', 'called', 'in_progress', 'completed', 'cancelled'];
$allowedType   = ['walkin', 'appointment'];

$fStatus = isset($_GET['status']) && in_array($_GET['status'], $allowedStatus, true) ? $_GET['status'] : '';
$fType   = isset($_GET['type']) && in_array($_GET['type'], $allowedType, true) ? $_GET['type'] : '';
$fFrom   = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$fTo     = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';
$q       = trim($_GET['q'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

/* ---------------------------------------------------------
   WHERE clause shared by list + count + stats
--------------------------------------------------------- */
$where  = ['qt.window_id = :windowId'];
$params = [':windowId' => $windowId];

if ($fStatus !== '') {
    $where[] = 'qt.status = :status';
    $params[':status'] = $fStatus;
}
if ($fType !== '') {
    $where[] = 'qt.type = :type';
    $params[':type'] = $fType;
}
if ($fFrom !== '') {
    $where[] = 'DATE(qt.joined_at) >= :from';
    $params[':from'] = $fFrom;
}
if ($fTo !== '') {
    $where[] = 'DATE(qt.joined_at) <= :to';
    $params[':to'] = $fTo;
}
if ($q !== '') {
    $where[] = "(qt.queue_number LIKE :q1 OR CONCAT(st.first_name,' ',st.last_name) LIKE :q2 OR st.sr_code LIKE :q3)";
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ---------------------------------------------------------
   Stats (unfiltered by status/type/date, scoped to window)
--------------------------------------------------------- */
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(qt.status = 'completed')  AS completed,
        SUM(qt.status = 'cancelled')  AS cancelled,
        SUM(qt.status = 'in_progress') AS in_progress,
        SUM(qt.status = 'waiting')    AS waiting
    FROM queue_tickets qt
    WHERE qt.window_id = :windowId
");
$statsStmt->execute([':windowId' => $windowId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => 0, 'completed' => 0, 'cancelled' => 0, 'in_progress' => 0, 'waiting' => 0,
];

/* ---------------------------------------------------------
   Total rows for pagination (filtered)
--------------------------------------------------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM queue_tickets qt
    LEFT JOIN students st ON st.id = qt.student_id
    $whereSql
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ---------------------------------------------------------
   Transaction list
--------------------------------------------------------- */
$listSql = "
    SELECT
        qt.id,
        qt.queue_number,
        qt.type,
        qt.status,
        qt.priority,
        qt.joined_at,
        qt.called_at,
        qt.done_at,
        st.first_name,
        st.last_name,
        st.sr_code,
        sf.name AS assigned_staff,
        sf.position AS assigned_staff_position,
        (
            SELECT GROUP_CONCAT(
                CONCAT(d.name, IF(qtd.quantity > 1, CONCAT(' x', qtd.quantity), ''))
                SEPARATOR '~~'
            )
            FROM queue_ticket_document qtd
            JOIN documents d ON d.id = qtd.document_id
            WHERE qtd.ticket_id = qt.id
        ) AS documents

    FROM queue_tickets qt
    LEFT JOIN students st ON st.id = qt.student_id
    LEFT JOIN staff sf ON sf.window_id = qt.window_id
    $whereSql
    ORDER BY qt.joined_at DESC
    LIMIT :limit OFFSET :offset
";

$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $listStmt->bindValue($key, $val);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$transactions = $listStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------
   Helpers
--------------------------------------------------------- */
function qt_status_label(string $status): string
{
    $labels = [
        'waiting'     => 'Waiting',
        'called'      => 'Called',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status ?: 'Unknown');
}

function qt_status_class(string $status): string
{
    $classes = [
        'waiting'     => 'waiting',
        'called'      => 'called',
        'in_progress' => 'progress',
        'completed'   => 'completed',
        'cancelled'   => 'cancelled',
    ];
    return $classes[$status] ?? 'unknown';
}

function qt_query(array $overrides = []): string
{
    $current = [
        'status' => $_GET['status'] ?? '',
        'type'   => $_GET['type'] ?? '',
        'from'   => $_GET['from'] ?? '',
        'to'     => $_GET['to'] ?? '',
        'q'      => $_GET['q'] ?? '',
        'page'   => $_GET['page'] ?? 1,
    ];
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return http_build_query($merged);
}

?>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<link rel="stylesheet" href="../../assets/css/staff-dashboard.css">
<link rel="stylesheet" href="../../assets/css/queue-transaction.css">
<link rel="stylesheet" href="/assets/css/header.css">
<link rel="stylesheet" href="/assets/css/footer.css">

<div class="qt-page">

    <!-- =========================
         HEADER
    ========================== -->
    <div class="qt-page__header">

        <div class="qt-page__heading">
            <h2>Queue Transactions</h2>
            <p class="qt-page__sub">
                History of tickets handled at
                <strong><?= htmlspecialchars($windowName) ?></strong>
                <?= $officeName ? ' &mdash; ' . htmlspecialchars($officeName) : '' ?>
            </p>
        </div>

        <div class="qt-stats">
            <div class="qt-stat">
                <span class="qt-stat__value"><?= (int)$stats['total'] ?></span>
                <span class="qt-stat__label">Total</span>
            </div>
            <div class="qt-stat qt-stat--completed">
                <span class="qt-stat__value"><?= (int)$stats['completed'] ?></span>
                <span class="qt-stat__label">Completed</span>
            </div>
            <div class="qt-stat qt-stat--progress">
                <span class="qt-stat__value"><?= (int)$stats['in_progress'] ?></span>
                <span class="qt-stat__label">In Progress</span>
            </div>
            <div class="qt-stat qt-stat--waiting">
                <span class="qt-stat__value"><?= (int)$stats['waiting'] ?></span>
                <span class="qt-stat__label">Waiting</span>
            </div>
            <div class="qt-stat qt-stat--cancelled">
                <span class="qt-stat__value"><?= (int)$stats['cancelled'] ?></span>
                <span class="qt-stat__label">Cancelled</span>
            </div>
        </div>

    </div>

    <!-- =========================
         FILTERS
    ========================== -->
    <form class="qt-filters" method="get" id="qtFilterForm">

        <div class="qt-filters__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
                type="text"
                name="q"
                id="qtSearch"
                placeholder="Search queue number or student name..."
                value="<?= htmlspecialchars($q) ?>"
                autocomplete="off">
        </div>

        <select name="status" id="qtStatus" class="qt-filters__select">
            <option value="">All Status</option>
            <?php foreach ($allowedStatus as $s): ?>
                <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>>
                    <?= qt_status_label($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="type" id="qtType" class="qt-filters__select">
            <option value="">All Types</option>
            <option value="walkin" <?= $fType === 'walkin' ? 'selected' : '' ?>>Walk-in</option>
            <option value="appointment" <?= $fType === 'appointment' ? 'selected' : '' ?>>Appointment</option>
        </select>

        <input type="date" name="from" id="qtFrom" class="qt-filters__date" value="<?= htmlspecialchars($fFrom) ?>">
        <span class="qt-filters__to">to</span>
        <input type="date" name="to" id="qtTo" class="qt-filters__date" value="<?= htmlspecialchars($fTo) ?>">

        <button type="submit" class="btn btn-primary qt-filters__submit">Filter</button>

        <?php if ($fStatus || $fType || $fFrom || $fTo || $q): ?>
            <a href="queue-transaction.php" class="btn btn-secondary qt-filters__clear">Clear</a>
        <?php endif; ?>

    </form>

    <!-- =========================
         TABLE
    ========================== -->
    <div class="qt-table-wrap">

        <?php if (empty($transactions)): ?>

            <div class="empty qt-empty">
                No transactions found<?= ($fStatus || $fType || $fFrom || $fTo || $q) ? ' for the selected filters.' : ' for this window yet.' ?>
            </div>

        <?php else: ?>

        <table class="qt-table">
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Student</th>
                    <th>Document(s)</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Assigned Staff</th>
                    <th>Joined</th>
                    <th>Called</th>
                    <th>Done</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $row): ?>
                    <?php
                        $studentName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        $docs = $row['documents'] ? explode('~~', $row['documents']) : [];
                    ?>
                    <tr>
                        <td class="qt-cell-qnum">
                            <?= htmlspecialchars($row['queue_number']) ?>
                            <?php if (!empty($row['priority'])): ?>
                                <span class="qt-priority" title="Priority ticket">★</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="qt-student">
                                <span class="qt-student__name"><?= htmlspecialchars($studentName ?: '—') ?></span>
                                <?php if (!empty($row['sr_code'])): ?>
                                    <span class="qt-student__sr"><?= htmlspecialchars($row['sr_code']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (empty($docs)): ?>
                                <span class="doc-tag doc-tag--empty">No document</span>
                            <?php else: ?>
                                <?php $visibleCount = 2; $extraCount = count($docs) - $visibleCount; ?>
                                <div class="qt-doc-list">
                                    <?php foreach ($docs as $i => $doc): ?>
                                        <span class="doc-tag<?= $i >= $visibleCount ? ' qt-doc-extra' : '' ?>"><?= htmlspecialchars($doc) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($extraCount > 0): ?>
                                        <button type="button" class="qt-doc-toggle" data-more="+<?= $extraCount ?> more" data-less="Show less">
                                            +<?= $extraCount ?> more
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="queue-type-badge queue-type-badge--<?= htmlspecialchars($row['type']) ?>">
                                <?= $row['type'] === 'appointment' ? 'Appointment' : 'Walk-in' ?>
                            </span>
                        </td>
                        <td>
                            <span class="qt-status qt-status--<?= qt_status_class($row['status']) ?>">
                                <?= qt_status_label($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($row['assigned_staff'])): ?>
                                <div class="qt-staff">
                                    <span class="qt-staff__name"><?= htmlspecialchars($row['assigned_staff']) ?></span>
                                    <?php if (!empty($row['assigned_staff_position'])): ?>
                                        <span class="qt-staff__pos"><?= htmlspecialchars($row['assigned_staff_position']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="qt-unassigned">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="qt-cell-time"><?= $row['joined_at'] ? date('M j, Y g:i A', strtotime($row['joined_at'])) : '—' ?></td>
                        <td class="qt-cell-time"><?= $row['called_at'] ? date('M j, Y g:i A', strtotime($row['called_at'])) : '—' ?></td>
                        <td class="qt-cell-time"><?= $row['done_at'] ? date('M j, Y g:i A', strtotime($row['done_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div>

    <!-- =========================
         PAGINATION
    ========================== -->
    <?php if ($totalPages > 1): ?>
    <div class="qt-pagination">
        <span class="qt-pagination__info">
            Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
        </span>
        <div class="qt-pagination__controls">
            <a href="?<?= qt_query(['page' => max(1, $page - 1)]) ?>"
               class="qt-pagination__btn <?= $page <= 1 ? 'is-disabled' : '' ?>">&laquo; Prev</a>

            <span class="qt-pagination__current">Page <?= $page ?> of <?= $totalPages ?></span>

            <a href="?<?= qt_query(['page' => min($totalPages, $page + 1)]) ?>"
               class="qt-pagination__btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>">Next &raquo;</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="../../assets/js/queue-transaction.js"></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>