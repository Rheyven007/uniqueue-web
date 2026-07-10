<?php
// admin/queue/office-dashboard.php — Office Admin Queue Dashboard
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_office_admin();

$office_id        = $_SESSION['office_id'] ?? null;
$target_office_id = $office_id;

// Office admin without an assigned office
if (!$target_office_id) {
    redirect('/auth/logout.php');
}

// Fetch office info
$stmt = $pdo->prepare("SELECT id, name FROM offices WHERE id = ?");
$stmt->execute([$target_office_id]);
$office = $stmt->fetch();
if (!$office) redirect('/auth/logout.php');

// ── Stats for THIS office, today ──────────────────────────────────────────────
$oid        = $target_office_id;
$today_stats = $pdo->prepare("
    SELECT
        COUNT(*)                                                    AS total,
        SUM(status = 'waiting')                                     AS waiting,
        SUM(status IN ('called','in_progress'))                     AS serving,
        SUM(status = 'done')                                        AS done,
        SUM(status = 'cancelled')                                   AS cancelled,
        SUM(type = 'priority' OR priority = 1)                     AS priority_count,
        SUM(type = 'appointment')                                   AS appointments,
        AVG(CASE WHEN done_at IS NOT NULL AND called_at IS NOT NULL
                 THEN TIMESTAMPDIFF(MINUTE, called_at, done_at) END) AS avg_service_min
    FROM queue_tickets
    WHERE office_id = ? AND DATE(joined_at) = CURDATE()
");
$today_stats->execute([$oid]);
$ts = $today_stats->fetch();

// ── Windows for this office ───────────────────────────────────────────────
$win_stmt = $pdo->prepare("
    SELECT
        w.*,

        (
            SELECT name
            FROM staff
            WHERE window_id = w.id
            LIMIT 1
        ) AS staff_name,

        (
            SELECT COUNT(*)
            FROM queue_tickets qt
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
              AND DATE(qt.joined_at) = CURDATE()
        ) AS active_tickets,

        (
            SELECT s.first_name
            FROM queue_tickets qt
            INNER JOIN students s
                ON s.id = qt.student_id
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
            ORDER BY qt.called_at DESC
            LIMIT 1
        ) AS current_student_fname,

        (
            SELECT qt.queue_number
            FROM queue_tickets qt
            WHERE qt.window_id = w.id
              AND qt.status IN ('called','in_progress')
            ORDER BY qt.called_at DESC
            LIMIT 1
        ) AS current_ticket_num

    FROM windows w
    WHERE w.office_id = ?
    ORDER BY w.name ASC
");

$win_stmt->execute([$oid]);
$windows = $win_stmt->fetchAll(PDO::FETCH_ASSOC);
    $win_stmt->execute([$oid]);
    $windows = $win_stmt->fetchAll();

$pageTitle = "Dashboard — " . $office['name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="od-wrap">

    <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
    <div class="od-topbar">
        <div class="od-topbar__left">
            <h1><?= htmlspecialchars($office['name']) ?></h1>
            <p>Queue Dashboard &nbsp;·&nbsp; <?= date('l, F j, Y') ?></p>
        </div>

        <div class="od-actions">
            <button id="refresh-btn" class="btn btn-green" onclick="location.reload()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10"/>
                    <polyline points="1 20 1 14 7 14"/>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
                Refresh
            </button>

         <a href="/admin/queue/queue-list.php?office_id=<?= $oid ?>" class="btn btn-ghost">
            Queue List
        </a>

        <a href="/admin/document/document-list.php" class="btn btn-ghost">
            Documents
        </a>

        <a href="/admin/counter/counter-list.php" class="btn btn-ghost">
            Manage Windows
        </a>

        <a href="/admin/staff/staff-list.php" class="btn btn-ghost">
            Manage Staff
        </a>

        <a href="/admin/capacity/capacity-settings.php?office_id=<?= $oid ?>" class="btn btn-ghost">
            Settings
        </a>
        </div>
    </div>

    <!-- ── Today's summary ──────────────────────────────────────────────────── -->
    <div class="sec-label">Today's summary</div>
    <div class="stats-row" role="list" aria-label="Today's queue statistics">

        <div class="stat-box s-blue" role="listitem">
            <div class="stat-box__lbl">Total Today</div>
            <div class="stat-box__val" aria-label="<?= (int)$ts['total'] ?> total tickets today">
                <?= (int)$ts['total'] ?>
            </div>
        </div>

        <div class="stat-box s-amber" role="listitem">
            <div class="stat-box__lbl">Waiting</div>
            <div class="stat-box__val" aria-label="<?= (int)$ts['waiting'] ?> waiting">
                <?= (int)$ts['waiting'] ?>
            </div>
        </div>

        <div class="stat-box s-teal" role="listitem">
            <div class="stat-box__lbl">Serving</div>
            <div class="stat-box__val" aria-label="<?= (int)$ts['serving'] ?> currently serving">
                <?= (int)$ts['serving'] ?>
            </div>
        </div>

        <div class="stat-box s-green" role="listitem">
            <div class="stat-box__lbl">Completed</div>
            <div class="stat-box__val" aria-label="<?= (int)$ts['done'] ?> completed">
                <?= (int)$ts['done'] ?>
            </div>
        </div>

        <div class="stat-box s-red" role="listitem">
            <div class="stat-box__lbl">Cancelled</div>
            <div class="stat-box__val" aria-label="<?= (int)$ts['cancelled'] ?> cancelled">
                <?= (int)$ts['cancelled'] ?>
            </div>
        </div>

        <div class="stat-box s-violet" role="listitem">
            <div class="stat-box__lbl">Avg. Serve (min)</div>
            <div class="stat-box__val">
                <?= $ts['avg_service_min'] ? round($ts['avg_service_min']) : '—' ?>
            </div>
        </div>

    </div><!-- /.stats-row -->

    <!-- ── Main grid ────────────────────────────────────────────────────────── -->
    <div class="od-grid">

        <!-- Left: windows ──────────────────────────────────────────────────── -->
        <aside class="windows-col" aria-label="Service windows">
            <div class="sec-label">Service Windows</div>

            <?php if (empty($windows)): ?>
                <p style="color:var(--dim);font-size:.85rem;padding:.5rem 0;">
                    No windows configured.
                </p>
            <?php endif; ?>

            <?php foreach ($windows as $w): ?>
            <article class="window-card is-<?= htmlspecialchars($w['status']) ?>">

                <div class="window-card__top">
                    <span class="window-card__name"><?= htmlspecialchars($w['name']) ?></span>
                    <span class="status-dot <?= htmlspecialchars($w['status']) ?>"
                          title="<?= ucfirst($w['status']) ?>"
                          aria-label="Status: <?= ucfirst($w['status']) ?>"></span>
                </div>

                <div class="window-card__meta">
                    Speed: <?= ucfirst($w['speed']) ?>
                    &nbsp;·&nbsp;
                    Status: <strong style="color: <?= $w['status'] === 'open' ? 'var(--green)' : 'var(--muted)' ?>">
                        <?= ucfirst($w['status']) ?>
                    </strong>

                       <?php if (!empty($w['staff_name'])): ?>
                    <br>
                    Staff:
                    <strong><?= htmlspecialchars($w['staff_name']) ?></strong>
                    <?php else: ?>
                        <br>
                        <span style="color:#dc2626;">
                            No Staff Assigned
                        </span>
                    <?php endif; ?>
                </div>

             

                <div class="window-card__serving">
                    <?php if ($w['current_ticket_num']): ?>
                        <div class="ticket-num"><?= htmlspecialchars($w['current_ticket_num']) ?></div>
                        <div class="student-name"><?= htmlspecialchars($w['current_student_fname'] ?? 'Student') ?></div>
                    <?php else: ?>
                        <span class="empty-slot">
                            <?= $w['status'] === 'open' ? 'Idle — ready for next' : 'Window closed' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <button
                    class="btn btn-ghost window-card__toggle btn-toggle-counter"
                    data-id="<?= (int)$w['id'] ?>"
                    data-status="<?= htmlspecialchars($w['status']) ?>"
                    aria-label="<?= $w['status'] === 'open' ? 'Close' : 'Open' ?> <?= htmlspecialchars($w['name']) ?>">
                    <?= $w['status'] === 'open' ? 'Close Window' : 'Open Window' ?>
                </button>

            </article>
            <?php endforeach; ?>
        </aside>

        <!-- Right: queue panels (JS-driven) ────────────────────────────────── -->
        <div class="queue-col">

            <!-- In Progress -->
            <section class="queue-section" aria-labelledby="serving-heading">
                <div class="queue-section__head">
                    <h2 id="serving-heading">
                        Called / In Progress
                        <span class="count-badge teal" id="serving-count" aria-live="polite">…</span>
                    </h2>
                </div>
                <div id="in-progress-queue-list" role="list" aria-label="Tickets being served">
                    <div class="empty-state">Loading…</div>
                </div>
            </section>

            <!-- Waiting -->
            <section class="queue-section" aria-labelledby="waiting-heading">
                <div class="queue-section__head">
                    <h2 id="waiting-heading">
                        Waiting Queue
                        <span class="count-badge amber" id="waiting-count" aria-live="polite">…</span>
                    </h2>
                    <button class="btn btn-primary btn-sm" id="smart-assign-btn">
                        Smart Assign
                    </button>
                </div>
                <div id="waiting-queue-list" role="list" aria-label="Students waiting">
                    <div class="empty-state">Loading…</div>
                </div>
            </section>

        </div><!-- /.queue-col -->
    </div><!-- /.od-grid -->
</div><!-- /.od-wrap -->

<link rel="stylesheet" href="/assets/css/office-dashboard.css">
<script>const CURRENT_OFFICE_ID = <?= (int)$target_office_id ?>;</script>
<script src="/assets/js/office-dashboard.js" defer></script>
<script src="/assets/js/smart-assign.js"     defer></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>