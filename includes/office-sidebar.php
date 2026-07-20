<?php
/**
 * includes/office-sidebar.php
 * ─────────────────────────────────────────────────────────────
 * Centralized Office Admin sidebar nav. Included by every admin
 * page (dashboard, queue list, staff, counters, documents, etc.)
 * so the nav only ever needs to be edited in ONE place.
 *
 * Usage — from any admin/*.php page:
 *
 *     <div class="app-shell">
 *         <?php include __DIR__ . '/../../includes/office-sidebar.php'; ?>
 *         <div class="od-wrap">
 *             ... page content ...
 *         </div>
 *     </div>
 *
 * Active link is detected automatically from the current script
 * path, so nothing needs to be passed in.
 * ───────────────────────────────────────────────────────────── */

// Basename of the file currently being rendered, e.g. "staff-list.php"
$__current = basename($_SERVER['SCRIPT_NAME']);

/**
 * Returns "active" if $files (string or array of filenames)
 * matches the page currently being rendered.
 */
function od_sidebar_active($current, $files) {
    foreach ((array) $files as $file) {
        if ($current === $file) return 'active';
    }
    return '';
}

// office_id may already be in scope from the including page ($oid / $office_id)
$__oid = $oid ?? ($office_id ?? ($_SESSION['office_id'] ?? null));
?>
<!-- Mobile-only hamburger trigger — shown/hidden via CSS media query -->
<button type="button"
        class="od-sidebar-toggle"
        id="od-sidebar-toggle"
        aria-label="Toggle navigation menu"
        aria-expanded="false"
        aria-controls="od-sidebar">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
</button>

<!-- Backdrop — click to close the drawer on mobile -->
<div class="od-sidebar-overlay" id="od-sidebar-overlay"></div>

<aside class="od-sidebar" id="od-sidebar" aria-label="Office admin navigation">
    <div class="od-sidebar__label">Office</div>

    <a href="/admin/queue/office-dashboard.php"
       class="od-sidebar__link <?= od_sidebar_active($__current, 'office-dashboard.php') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        Dashboard
    </a>

    <a href="/admin/queue/queue-list.php?office_id=<?= (int) $__oid ?>"
       class="od-sidebar__link <?= od_sidebar_active($__current, 'queue-list.php') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Queue List
    </a>

    <a href="/admin/document/document-list.php"
       class="od-sidebar__link <?= od_sidebar_active($__current, 'document-list.php') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
        Documents
    </a>

    <a href="/admin/counter/counter-list.php"
       class="od-sidebar__link <?= od_sidebar_active($__current, 'counter-list.php') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="18" x2="12" y2="21"/></svg>
        Manage Windows
    </a>

    <a href="/admin/staff/staff-list.php"
       class="od-sidebar__link <?= od_sidebar_active($__current, ['staff-list.php', 'staff-add.php', 'staff-edit.php']) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Manage Staff
    </a>

    <div class="od-sidebar__divider"></div>

    <a href="/admin/capacity/capacity-settings.php?office_id=<?= (int) $__oid ?>"
       class="od-sidebar__link <?= od_sidebar_active($__current, 'capacity-settings.php') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Settings
    </a>
</aside>

<script>
(function () {
    var btn     = document.getElementById('od-sidebar-toggle');
    var sidebar = document.getElementById('od-sidebar');
    var overlay = document.getElementById('od-sidebar-overlay');
    if (!btn || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function () {
        sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    // Close the drawer once a nav link is tapped
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    // If the viewport grows back to desktop size, reset to the docked state
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) closeSidebar();
    });
})();
</script>