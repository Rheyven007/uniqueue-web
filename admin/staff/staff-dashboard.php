<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

$staffId = $_SESSION['staff_id'];

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

$staffName = $staff['name'] ?? 'Staff';
$windowName = $staff['window_name'] ?? 'No Window Assigned';
$officeName = $staff['office_name'] ?? '';
$windowQueueType = $staff['queue_type'] ?? 'both';

switch ($windowQueueType) {
    case 'walkin':
        $windowQueueTypeLabel = 'Walk-in Only';
        $queueTypeBadgeClass = 'walkin';
        break;

    case 'appointment':
        $windowQueueTypeLabel = 'Appointment Only';
        $queueTypeBadgeClass = 'appointment';
        break;

    default:
        $windowQueueTypeLabel = 'Walk-in & Appointment';
        $queueTypeBadgeClass = 'both';
}


?>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<link rel="stylesheet" href="../../assets/css/staff-dashboard.css">

<div class="staff-dashboard">

    <!-- =========================
         HEADER
    ========================== -->
    <div class="staff-dashboard__header">

        <div class="staff-dashboard__heading">

            <div class="staff-dashboard__eyebrow">
                <span class="live-dot" aria-hidden="true"></span>
                Live Queue
            </div>

            <h2>Staff Queue Dashboard</h2>

            <p class="staff-dashboard__window">
                <?= htmlspecialchars($officeName ?: 'Office') ?> Staff:
                <strong><?= htmlspecialchars($staffName) ?></strong>
            </p>
        </div>

        <!-- Window / office / queue-type + live counts, all together
             on the right side of the header. -->
        <div class="staff-dashboard__header-right">

            <div class="header-info-chips" id="staffWindowInfo">

                <span class="info-pill info-pill--window" id="windowNamePill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="14" rx="2"/>
                        <path d="M8 21h8"/>
                        <path d="M12 18v3"/>
                    </svg>
                    <span id="windowNameText"><?= htmlspecialchars($windowName) ?></span>
                </span>

                <?php if (!empty($officeName)): ?>
                    <span class="info-pill info-pill--office">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 21V7a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v14"/>
                            <path d="M14 21V11a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v10"/>
                            <path d="M9 9h.01"/>
                            <path d="M9 13h.01"/>
                            <path d="M9 17h.01"/>
                        </svg>
                        <?= htmlspecialchars($officeName) ?>
                    </span>
                <?php endif; ?>

                <span class="info-pill info-pill--queuetype info-pill--<?= $queueTypeBadgeClass ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 6h13"/>
                        <path d="M8 12h13"/>
                        <path d="M8 18h13"/>
                        <circle cx="4" cy="6" r="1"/>
                        <circle cx="4" cy="12" r="1"/>
                        <circle cx="4" cy="18" r="1"/>
                    </svg>
                    <?= htmlspecialchars($windowQueueTypeLabel) ?>
                </span>

            </div>

        </div>

    </div>

    <!-- =========================
         MAIN GRID
    ========================== -->

    <div class="staff-dashboard__grid">

        <!-- LEFT PANEL -->
        <div class="staff-dashboard__left">

            <!-- CURRENT SERVING -->
            <section class="staff-dashboard__section staff-dashboard__section--current">

                <h3>
                    <span class="section-title-text">Current Serving</span>
                    <span class="section-title-badge" id="currentQueueTypeBadge"></span>
                </h3>

                <div id="current-ticket">

                    <div class="current-ticket-card">

                        <div class="empty">
                            Loading current customer...
                        </div>

                    </div>

                </div>

            </section>

            <!-- NEXT IN QUEUE -->
            <section class="staff-dashboard__section staff-dashboard__section--next">

                <h3>
                    <span class="section-title-text">Next in Queue</span>
                    <span class="section-title-badge" id="nextQueueTypeBadge"></span>
                </h3>

                <div id="next-ticket">

                    <div class="current-ticket-card">

                        <div class="empty">
                            Loading next customer...
                        </div>

                    </div>

                </div>

            </section>

            <!-- ACTION BUTTONS -->
            <section class="staff-dashboard__section staff-dashboard__section--actions">

                <h3>Queue Controls</h3>

                <div class="staff-actions">

                    <button
                        id="callNextBtn"
                        class="btn btn-primary"
                        type="button">

                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">

                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>

                        </svg>

                        Call Next

                    </button>

                    <button
                        id="startServiceBtn"
                        class="btn btn-success"
                        type="button">

                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">

                            <polygon points="5 3 19 12 5 21 5 3"/>

                        </svg>

                        Start Service

                    </button>

                    <button
                        id="doneBtn"
                        class="btn btn-danger"
                        type="button">

                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">

                            <polyline points="20 6 9 17 4 12"/>

                        </svg>

                        Complete

                    </button>

                </div>

            </section>

        </div>

        <!-- RIGHT PANEL -->
        <div class="staff-dashboard__right">

            <!-- WAITING QUEUE -->
            <section class="staff-dashboard__section staff-dashboard__queue">

                <div class="queue-header">

                    <h3>Waiting Queue</h3>

                    <span
                        class="queue-count"
                        id="queueCount">

                        0 Customers

                    </span>

                </div>

                <div id="waiting-list">

                    <div class="empty">

                        Loading queue...

                    </div>

                </div>

            </section>

            <!-- LAST IN LINE -->
            <section class="staff-dashboard__section staff-dashboard__section--last">

                <h3>Last in Line</h3>

                <div id="last-ticket">

                    <div class="empty">
                        No one else waiting.
                    </div>

                </div>

            </section>

        </div>

    </div>

</div>

<script>
    // Make the logged-in staff name available to JavaScript if needed.
    window.staffName = <?= json_encode($staffName) ?>;
</script>

<script src="../../assets/js/staff-dashboard.js"></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>