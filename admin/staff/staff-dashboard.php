
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
                    • <?= htmlspecialchars($windowName) ?>

                    <?php if (!empty($officeName)): ?>
                        (<?= htmlspecialchars($officeName) ?>)
                    <?php endif; ?>

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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
```
