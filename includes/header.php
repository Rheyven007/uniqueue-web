<?php
// includes/header.php — Shared HTML header / nav
// Expects session.php already included by the calling page.
?>
<header class="site-header">
    <link rel="stylesheet" href="/assets/css/office-dashboard.css">
    <div class="site-header__inner">

        <!-- Brand -->
        <a href="<?= is_student_logged_in() ? '/student/dashboard.php' : '/admin/queue/office-dashboard.php' ?>"
           class="site-header__brand" aria-label="Uniqueue home">
            <img src="/assets/img/logo.png" alt="" class="site-header__logo" aria-hidden="true">
            <span class="site-header__name">Uniqueue</span>
        </a>

        <?php if (is_student_logged_in()): ?>
        <!-- Student nav -->
        <nav class="site-nav" aria-label="Student navigation">
            <a href="/student/dashboard.php"            class="site-nav__link">Dashboard</a>
            <a href="/student/student-transaction.php"  class="site-nav__link">Transactions</a>
        </nav>

        <div class="site-header__user">
            <span class="site-header__user-name"><?= e($_SESSION['student_name']) ?></span>

            <!-- Notification bell -->
            <button class="notif-bell" id="notif-bell"
                    aria-label="Notifications" aria-expanded="false" aria-controls="notif-dropdown">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     width="18" height="18" aria-hidden="true">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-badge" id="notif-badge" hidden aria-label="New notifications"></span>
            </button>

            <a href="/auth/logout.php" class="btn btn-ghost btn-sm">Log Out</a>
        </div>

        <?php elseif (is_admin_logged_in()): ?>
        <!-- Admin nav -->
        <nav class="site-nav" aria-label="Admin navigation">
            <?php if (!empty($_SESSION['is_super_admin'])): ?>
                <a href="/admin/dashboard.php"               class="site-nav__link">Overview</a>
                <a href="/admin/office/office-list.php"      class="site-nav__link">Offices</a>
                <a href="/admin/reports/reports-daily.php"   class="site-nav__link">Reports</a>
                <a href="/admin/feedback/feedback-list.php"  class="site-nav__link">Feedback</a>
                <?php if (!empty($_SESSION['office_id'])): ?>
                    <a href="/admin/queue/office-dashboard.php" class="site-nav__link">My Office</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="site-header__user">
            <span class="site-header__user-name"><?= e($_SESSION['admin_username']) ?></span>
            <a href="/auth/logout.php" class="btn btn-ghost btn-sm">Log Out</a>
        </div>
        <?php endif; ?>

    </div><!-- /.site-header__inner -->

    <!-- Notification dropdown (populated by notifications.js) -->
    <?php if (is_student_logged_in()): ?>
    <div class="notif-dropdown" id="notif-dropdown"
         hidden role="dialog" aria-label="Notifications" aria-modal="true">
        <div class="notif-dropdown__header">
            <span>Notifications</span>
            <button class="notif-dropdown__close" id="notif-close"
                    aria-label="Close notifications">&times;</button>
        </div>
        <ul class="notif-list" id="notif-list" role="list">
            <li class="notif-list__empty">No new notifications</li>
        </ul>
    </div>
    <?php endif; ?>

</header>