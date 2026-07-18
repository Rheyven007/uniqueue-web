<?php
// includes/header.php — Shared HTML header / nav
// Expects session.php already included by the calling page.
?>
<header class="site-header<?= (function_exists('is_staff_logged_in') && is_staff_logged_in()) ? ' site-header--staff' : '' ?>">
    <link rel="stylesheet" href="/assets/css/office-dashboard.css">
    <div class="site-header__inner">

        <!-- Brand -->
        <?php
        $brandHref = '/admin/queue/office-dashboard.php';
        if (is_student_logged_in()) {
            $brandHref = '/student/dashboard.php';
        } elseif (is_staff_logged_in()) {
            $brandHref = '/admin/queue/staff-dashboard.php';
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

        <?php elseif (is_staff_logged_in()): ?>
        <!-- Staff header: full-width solid banner with a live
             date/time readout sitting directly beside the logout
             button on the right. -->
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

        <style>
            /* Full-bleed solid banner for the staff header, matching
               the solid-color treatment used on the staff dashboard
               (no gradients, edge-to-edge width). Scoped to
               .site-header--staff so student/admin views are
               untouched. */
            .site-header--staff{
                background:var(--bg, #f1ebeb) !important;
                width:100% !important;
                margin:0;
                border-radius:0 !important;
                border-bottom:1px solid rgba(102,8,16,.10);
            }
            .site-header--staff .site-header__inner{
                max-width:none;
                width:100%;
                padding:14px 24px;
                box-sizing:border-box;
            }
            .site-header--staff .site-header__name,
            .site-header--staff .site-header__brand{
                color:var(--brand-primary, #660810);
            }

            .site-header__right{
                display:flex;
                align-items:center;
                gap:12px;
                margin-left:auto;
            }

            .site-header__datetime{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:8px;
                min-width:200px;
                height:38px;
                padding:0 14px;
                border-radius:999px;
                background:rgba(102,8,16,.06);
                border:1px solid rgba(102,8,16,.16);
                color:var(--brand-primary, #660810);
                font-weight:600;
                line-height:1.1;
                white-space:nowrap;
                box-sizing:border-box;
            }
            .site-header__datetime-icon{
                flex:0 0 auto;
                color:rgba(102,8,16,.6);
            }
            .site-header__time{
                font-size:14px;
                font-weight:800;
                letter-spacing:.3px;
                font-variant-numeric:tabular-nums;
            }
            .site-header__date{
                font-size:12px;
                font-weight:600;
                color:rgba(102,8,16,.55);
                padding-left:8px;
                border-left:1px solid rgba(102,8,16,.16);
            }

            .site-header__logout{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:8px;
                min-width:80px;
                height:38px;
                padding:0 18px;
                border-radius:999px;
                background:rgba(102,8,16,.08);
                border:1px solid rgba(102,8,16,.20);
                color:var(--brand-primary, #660810);
                font-weight:700;
                font-size:13px;
                text-decoration:none;
                white-space:nowrap;
                box-sizing:border-box;
                transition:background .2s ease,color .2s ease,transform .2s ease,box-shadow .2s ease;
            }
            .site-header__logout:hover{
                background:var(--brand-primary, #660810);
                color:#fff;
                transform:translateY(-2px);
                box-shadow:0 10px 20px rgba(102,8,16,.25);
            }
            .site-header__logout:active{
                transform:translateY(0);
                box-shadow:none;
            }
            .site-header__logout svg{
                flex:0 0 auto;
            }

            @media (max-width:640px){
                .site-header__date{ display:none; }
                .site-header__logout span{ display:none; }
                .site-header__datetime,
                .site-header__logout{
                    min-width:0;
                }
                .site-header__logout{ padding:0 12px; }
            }
        </style>

        <script>
            (function () {
                var timeEl = document.getElementById('siteHeaderTime');
                var dateEl = document.getElementById('siteHeaderDate');
                if (!timeEl || !dateEl) return;

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
            })();
        </script>
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