<?php
// includes/header.php — Shared HTML header / nav
// Expects session.php already included by the calling page.

$isThemedHeader =
    (function_exists('is_staff_logged_in')   && is_staff_logged_in())
    || (function_exists('is_student_logged_in') && is_student_logged_in())
    || (function_exists('is_admin_logged_in')   && is_admin_logged_in());
?>
<header class="site-header<?= $isThemedHeader ? ' site-header--themed' : '' ?>">
    <link rel="stylesheet" href="/assets/css/office-dashboard.css">
    <div class="site-header__inner">

        <!-- Brand -->
        <?php
        $brandHref = '/admin/queue/office-dashboard.php';
        if (is_student_logged_in()) {
            $brandHref = '/student/dashboard.php';
        } elseif (is_staff_logged_in()) {
            $brandHref = '/admin/staff/staff-dashboard.php';
        }
        ?>
        <a href="<?= $brandHref ?>"
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

        <div class="site-header__right">

            <!-- Name + arrow dropdown → My Profile -->
            <div class="profile-menu" id="profileMenu">
                <button class="profile-menu__trigger" id="profileMenuTrigger"
                        type="button" aria-haspopup="true" aria-expanded="false"
                        aria-controls="profileMenuDropdown">
                    <span class="site-header__user-name"><?= e($_SESSION['student_name']) ?></span>
                    <svg class="profile-menu__chevron" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="profile-menu__dropdown" id="profileMenuDropdown" hidden role="menu">
                    <a href="/student/profile.php" class="profile-menu__item" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        My Profile
                    </a>
                </div>
            </div>

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

            <div class="site-header__datetime" id="siteHeaderDateTime" aria-live="off">
                <svg class="site-header__datetime-icon" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <polyline points="12 7 12 12 15.5 14"/>
                </svg>
                <span class="site-header__time" id="siteHeaderTime">--:--:--</span>
                <span class="site-header__date" id="siteHeaderDate">&mdash;</span>
            </div>

            <a href="/auth/logout.php" class="site-header__logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span>Log Out</span>
            </a>

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

        <div class="site-header__right">

            <span class="site-header__user-name"><?= e($_SESSION['admin_username']) ?></span>

            <div class="site-header__datetime" id="siteHeaderDateTime" aria-live="off">
                <svg class="site-header__datetime-icon" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <polyline points="12 7 12 12 15.5 14"/>
                </svg>
                <span class="site-header__time" id="siteHeaderTime">--:--:--</span>
                <span class="site-header__date" id="siteHeaderDate">&mdash;</span>
            </div>

            <a href="/auth/logout.php" class="site-header__logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span>Log Out</span>
            </a>

        </div>

        <?php elseif (is_staff_logged_in()): ?>
        <!-- Staff nav — Dashboard / Transactions rendered as pill buttons,
             anchored to the left, right next to the Uniqueue brand. -->
        <?php $staffCurrentPage = basename($_SERVER['SCRIPT_NAME']); ?>
        <nav class="site-nav site-nav--staff" aria-label="Staff navigation">
            <a href="/admin/staff/staff-dashboard.php"
               class="site-nav__button<?= $staffCurrentPage === 'staff-dashboard.php' ? ' is-active' : '' ?>"
               <?= $staffCurrentPage === 'staff-dashboard.php' ? 'aria-current="page"' : '' ?>>
                Dashboard
            </a>
            <a href="/admin/queue/queue-transaction.php"
               class="site-nav__button<?= $staffCurrentPage === 'queue-transaction.php' ? ' is-active' : '' ?>"
               <?= $staffCurrentPage === 'queue-transaction.php' ? 'aria-current="page"' : '' ?>>
                Transactions
            </a>
        </nav>

        <!-- Staff header: live date/time readout sitting directly
             beside the logout button on the right. -->
        <div class="site-header__right">

            <div class="site-header__datetime" id="siteHeaderDateTime" aria-live="off">
                <svg class="site-header__datetime-icon" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <polyline points="12 7 12 12 15.5 14"/>
                </svg>
                <span class="site-header__time" id="siteHeaderTime">--:--:--</span>
                <span class="site-header__date" id="siteHeaderDate">&mdash;</span>
            </div>

            <a href="/auth/logout.php" class="site-header__logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span>Log Out</span>
            </a>

        </div>

        <?php endif; ?>

    </div><!-- /.site-header__inner -->

    <?php if ($isThemedHeader): ?>
    <script>
        (function () {
            var timeEl = document.getElementById('siteHeaderTime');
            var dateEl = document.getElementById('siteHeaderDate');
            if (timeEl && dateEl) {
                function render() {
                    var now = new Date();
                    timeEl.textContent = now.toLocaleTimeString(undefined, {
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    dateEl.textContent = now.toLocaleDateString(undefined, {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                }
                render();
                setInterval(render, 1000);
            }

            // Name → arrow dropdown (My Profile)
            var trigger  = document.getElementById('profileMenuTrigger');
            var dropdown = document.getElementById('profileMenuDropdown');
            if (!trigger || !dropdown) return;

            function closeMenu() {
                dropdown.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
            function openMenu() {
                dropdown.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
            }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                if (dropdown.hidden) openMenu(); else closeMenu();
            });
            document.addEventListener('click', function (e) {
                if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== trigger) {
                    closeMenu();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMenu();
            });
        })();
    </script>
    <?php endif; ?>

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