<?php
// admin/dashboard.php — Super Admin System Overview
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/db.php';
require_super_admin();

// ── System-wide stats ─────────────────────────────────────────────────────────
$stats = [
    'offices'   => $pdo->query("SELECT COUNT(*) FROM offices WHERE is_active = 1")->fetchColumn(),
    'windows'   => $pdo->query("SELECT COUNT(*) FROM windows WHERE status = 'open'")->fetchColumn(),
    'tickets'   => $pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE DATE(joined_at) = CURDATE()")->fetchColumn(),
    'waiting'   => $pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE status = 'waiting' AND DATE(joined_at) = CURDATE()")->fetchColumn(),
    'serving'   => $pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE status IN ('called','in_progress') AND DATE(joined_at) = CURDATE()")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE status = 'done' AND DATE(joined_at) = CURDATE()")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE status = 'cancelled' AND DATE(joined_at) = CURDATE()")->fetchColumn(),
    'students'  => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
];

// ── Per-office breakdown ───────────────────────────────────────────────────────
$offices_data = $pdo->query("
    SELECT
        o.id,
        o.name,
        o.description,
        (SELECT COUNT(*) FROM windows  WHERE office_id = o.id AND status = 'open')  AS open_windows,
        (SELECT COUNT(*) FROM windows  WHERE office_id = o.id)                      AS total_windows,
        (SELECT COUNT(*) FROM queue_tickets WHERE office_id = o.id AND status = 'waiting'       AND DATE(joined_at) = CURDATE()) AS waiting_count,
        (SELECT COUNT(*) FROM queue_tickets WHERE office_id = o.id AND status IN ('called','in_progress') AND DATE(joined_at) = CURDATE()) AS serving_count,
        (SELECT COUNT(*) FROM queue_tickets WHERE office_id = o.id AND status = 'done'          AND DATE(joined_at) = CURDATE()) AS done_count,
        (SELECT COUNT(*) FROM queue_tickets WHERE office_id = o.id AND DATE(joined_at) = CURDATE()) AS total_today,
        (SELECT COUNT(*) FROM documents   WHERE office_id = o.id)                   AS doc_count
    FROM offices o
    WHERE o.is_active = 1
    ORDER BY o.name ASC
")->fetchAll();

// ── Recent tickets across all offices (last 8) ────────────────────────────────
$recent_tickets = $pdo->query("
    SELECT qt.queue_number, qt.status, qt.type, qt.priority, qt.joined_at,
           s.first_name, s.last_name, s.sr_code,
           o.name AS office_name
    FROM queue_tickets qt
    JOIN students s ON qt.student_id = s.id
    JOIN offices  o ON qt.office_id  = o.id
    ORDER BY qt.joined_at DESC
    LIMIT 8
")->fetchAll();

$pageTitle = "System Overview";
include __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Uniqueue — System Overview</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin-dashboard.css">
<div class="dash-wrap">

    <!-- Header -->
    <header class="dash-header">
        <div class="dash-header__left">
            <h1>System Overview</h1>
            <p>Today — <?= date('l, F j, Y') ?> &nbsp;·&nbsp; Logged in as <strong><?= e($_SESSION['admin_username']) ?></strong></p>
        </div>
        <div class="dash-header__right">
            <form action="/admin/reports/export-excel.php" method="GET" style="display:flex; gap:.5rem; align-items:center; background:var(--bg2); padding:.5rem; border-radius:var(--radius-sm); border:1px solid var(--border);">
                <span style="font-size:.75rem; color:var(--text-muted); margin-right:.25rem;">Export Range:</span>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="btn btn-ghost" style="padding:.3rem .5rem; font-size:.75rem;">
                <span style="color:var(--text-muted);">→</span>
                <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" class="btn btn-ghost" style="padding:.3rem .5rem; font-size:.75rem;">
                <button type="submit" class="btn btn-primary" style="padding:.35rem .8rem; font-size:.75rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:2px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    CSV
                </button>
            </form>
        </div>
    </header>

    <!-- Hero stats -->
    <div class="section-label">Today at a glance</div>
    <div class="hero-stats">
        <div class="stat-tile c-blue">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Active Offices
            </div>
            <div class="stat-tile__value"><?= $stats['offices'] ?></div>
        </div>
        <div class="stat-tile c-teal">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Open Windows
            </div>
            <div class="stat-tile__value"><?= $stats['windows'] ?></div>
        </div>
        <div class="stat-tile c-violet">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Total Tickets
            </div>
            <div class="stat-tile__value"><?= $stats['tickets'] ?></div>
        </div>
        <div class="stat-tile c-amber">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Waiting
            </div>
            <div class="stat-tile__value"><?= $stats['waiting'] ?></div>
        </div>
        <div class="stat-tile c-green">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Completed
            </div>
            <div class="stat-tile__value"><?= $stats['completed'] ?></div>
        </div>
        <div class="stat-tile c-red">
            <div class="stat-tile__label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cancelled
            </div>
            <div class="stat-tile__value"><?= $stats['cancelled'] ?></div>
        </div>
    </div>

    <!-- Main content -->
    <div class="dash-cols">

        <!-- Offices grid -->
        <div>
            <div class="section-label">Office Performance</div>
            <div class="offices-grid">
                <?php foreach ($offices_data as $o):
                    $pct = $o['total_today'] > 0
                        ? round(($o['done_count'] / $o['total_today']) * 100)
                        : 0;
                    $has_open = $o['open_windows'] > 0;
                ?>
                <div class="office-card">
                    <div class="office-card__header">
                        <div>
                            <div class="office-card__name"><?= e($o['name']) ?></div>
                            <?php if ($o['description']): ?>
                                <div class="office-card__desc"><?= e(mb_strimwidth($o['description'], 0, 70, '…')) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="windows-badge <?= $has_open ? 'has-open' : 'no-open' ?>">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>
                            <?= $o['open_windows'] ?>/<?= $o['total_windows'] ?>
                        </span>
                    </div>

                    <div class="office-card__stats">
                        <div class="mini-stat">
                            <span class="mini-stat__val amber"><?= $o['waiting_count'] ?></span>
                            <span class="mini-stat__lbl">Waiting</span>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-stat__val teal"><?= $o['serving_count'] ?></span>
                            <span class="mini-stat__lbl">Serving</span>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-stat__val green"><?= $o['done_count'] ?></span>
                            <span class="mini-stat__lbl">Done</span>
                        </div>
                    </div>

                    <!-- Completion progress -->
                    <div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
                            <span style="font-size:.72rem;color:var(--text-muted);">Completion rate today</span>
                            <span style="font-size:.72rem;color:var(--text-muted);"><?= $o['total_today'] ?> total</span>
                        </div>
                        <div class="progress-row">
                            <div class="progress-bar-track">
                                <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="progress-pct"><?= $pct ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent activity -->
        <div>
            <div class="section-label">Recent Activity</div>
            <div class="activity-panel">
                <div class="activity-panel__head">
                    <h2>Latest Tickets</h2>
                    <span class="live-dot" title="Live"></span>
                </div>
                <ul class="activity-list">
                    <?php if (empty($recent_tickets)): ?>
                        <li class="activity-item">
                            <span style="color:var(--text-muted);font-size:.85rem;">No tickets yet today.</span>
                        </li>
                    <?php else: ?>
                    <?php foreach ($recent_tickets as $t): ?>
                        <li class="activity-item">
                            <div class="activity-item__top">
                                <span class="activity-item__name"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></span>
                                <span class="activity-item__number"><?= e($t['queue_number']) ?></span>
                            </div>
                            <div class="activity-item__meta">
                                <span class="pill pill-<?= $t['status'] ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
                                <span class="pill pill-<?= $t['type'] ?>"><?= ucfirst($t['type']) ?></span>
                                <?php if ($t['priority']): ?>
                                    <span class="pill pill-priority">Priority</span>
                                <?php endif; ?>
                                <span>· <?= e($t['office_name']) ?></span>
                            </div>
                            <div style="font-size:.72rem;color:var(--text-dim);">
                                <?= date('h:i A', strtotime($t['joined_at'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

    </div><!-- /.dash-cols -->
</div><!-- /.dash-wrap -->

<?php include __DIR__ . '/../includes/footer.php'; ?>