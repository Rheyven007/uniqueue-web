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
        break;

    case 'appointment':
        $windowQueueTypeLabel = 'Appointment Only';
        break;

    default:
        $windowQueueTypeLabel = 'Walk-in & Appointment';
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
            <h2>Staff Queue Dashboard</h2>

            <p class="staff-dashboard__window">
                Welcome,
                <strong><?= htmlspecialchars($staffName) ?></strong>

                <span id="staffWindowInfo">
                    <span class="staff-window-pill">
                        <?= htmlspecialchars($windowName) ?>
                    </span>

                    <?php if (!empty($officeName)): ?>
                        <span class="staff-office-pill">
                            <?= htmlspecialchars($officeName) ?>
                        </span>
                    <?php endif; ?>

                    <span class="staff-office-pill">
                        <?= htmlspecialchars($windowQueueTypeLabel) ?>
                    </span>

                </span>
            </p>
        </div>

        <a href="../../auth/logout.php" class="logout-btn">
            <svg width="18"
                 height="18"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>

            Logout
        </a>

    </div>

    <!-- =========================
         QUEUE STATISTICS
    ========================== -->

    <div class="staff-stats-bar">

        <div class="stat-tile stat-tile--waiting">
            <div class="stat-tile__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M1 21v-2a4 4 0 0 1 3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M9 15a4 4 0 0 0-4 4v2h10v-2a4 4 0 0 0-4-4z"/>
                </svg>
            </div>
            <div class="stat-tile__body">
                <span class="stat-tile__label">Waiting in Your Queue</span>
                <span class="stat-tile__value" id="statWaitingCount">0</span>
            </div>
        </div>

        <div class="stat-tile stat-tile--serving">
            <div class="stat-tile__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/>
                    <path d="M12 4h9"/>
                    <path d="M4 4h.01"/>
                    <path d="M4 12h.01"/>
                    <path d="M4 20h.01"/>
                    <path d="M9 12h12"/>
                </svg>
            </div>
            <div class="stat-tile__body">
                <span class="stat-tile__label">Now Serving</span>
                <span class="stat-tile__value" id="statNowServing">—</span>
            </div>
        </div>

        <div class="stat-tile stat-tile--window">
            <div class="stat-tile__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                    <path d="M3 10h18"/>
                    <path d="M8 2v4"/>
                    <path d="M16 2v4"/>
                </svg>
            </div>
            <div class="stat-tile__body">
                <span class="stat-tile__label">Assigned Window</span>
                <span class="stat-tile__value">
                    <?= htmlspecialchars($windowName) ?>
                    <?php if (!empty($officeName)): ?>
                        <small><?= htmlspecialchars($officeName) ?> Office</small><br>
                    <?php endif; ?>
                </span>
            </div>
            
        </div>

        <div class="stat-tile stat-tile--queue-type">

    <div class="stat-tile__icon">
        <svg width="24"
             height="24"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round">

            <path d="M8 6h13"/>
            <path d="M8 12h13"/>
            <path d="M8 18h13"/>
            <circle cx="4" cy="6" r="1"/>
            <circle cx="4" cy="12" r="1"/>
            <circle cx="4" cy="18" r="1"/>

        </svg>
    </div>

    <div class="stat-tile__body">

        <span class="stat-tile__label">
            Queue Type Handled
        </span>

        <span class="stat-tile__value">
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
            <section class="staff-dashboard__section">

                <h3>Current Serving</h3>

                <div id="current-ticket">

                    <div class="current-ticket-card">

                        <div class="empty">
                            Loading current customer...
                        </div>

                    </div>

                </div>

            </section>

            <!-- NEXT IN QUEUE -->
            <section class="staff-dashboard__section">

                <h3>Next in Queue</h3>

                <div id="next-ticket">

                    <div class="current-ticket-card">

                        <div class="empty">
                            Loading next customer...
                        </div>

                    </div>

                </div>

            </section>

        </div>

        <!-- RIGHT PANEL -->
        <div class="staff-dashboard__right">

            <!-- ACTION BUTTONS -->
            <section class="staff-dashboard__section">

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

        </div>

    </div>

</div>

<script>
    // Make the logged-in staff name available to JavaScript if needed.
    window.staffName = <?= json_encode($staffName) ?>;
</script>

<script src="../../assets/js/staff-dashboard.js"></script>

<script>
    // Keeps the new "Queue Statistics" tiles in sync with the data
    // that staff-dashboard.js already renders into #queueCount and
    // #current-ticket, so no changes to that script are required.
    (function () {
        var waitingStat   = document.getElementById('statWaitingCount');
        var servingStat   = document.getElementById('statNowServing');
        var queueCountEl  = document.getElementById('queueCount');
        var currentTicket = document.getElementById('current-ticket');

        function extractNumber(text) {
            var match = (text || '').match(/\d+/);
            return match ? match[0] : '0';
        }

        function syncWaiting() {
            if (queueCountEl && waitingStat) {
                waitingStat.textContent = extractNumber(queueCountEl.textContent);
            }
        }

        function syncServing() {
            if (!currentTicket || !servingStat) return;
            var heading = currentTicket.querySelector('h2');
            servingStat.textContent = heading ? heading.textContent.trim() : '—';
        }

        if (queueCountEl) {
            new MutationObserver(syncWaiting).observe(queueCountEl, { childList: true, characterData: true, subtree: true });
            syncWaiting();
        }

        if (currentTicket) {
            new MutationObserver(syncServing).observe(currentTicket, { childList: true, characterData: true, subtree: true });
            syncServing();
        }
    })();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>