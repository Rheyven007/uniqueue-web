// assets/js/notifications.js — Notification polling and bell dropdown

(function () {
    'use strict';

    var bell       = document.getElementById('notif-bell');
    var badge      = document.getElementById('notif-badge');
    var dropdown   = document.getElementById('notif-dropdown');
    var closeBtn   = document.getElementById('notif-close');
    var list       = document.getElementById('notif-list');

    if (!bell || !dropdown || !list) return;

    var pollInterval = 30000; // 30 seconds
    var seenIds      = new Set();

    // ── Toggle dropdown ──────────────────────────────────────────────────────
    bell.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = !dropdown.hidden;
        dropdown.hidden = isOpen;
        bell.setAttribute('aria-expanded', String(!isOpen));

        if (!isOpen) {
            // Mark all as read visually when opening
            if (badge) badge.hidden = true;
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            dropdown.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        });
    }

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== bell) {
            dropdown.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    // ── Poll for notifications ────────────────────────────────────────────────
    fetchNotifications();
    setInterval(fetchNotifications, pollInterval);

    function fetchNotifications() {
        fetch('/api/get-notifications.php')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !Array.isArray(data.notifications)) {
                    if (data.error === 'Unauthorized') return; // User logged out
                    return;
                }
                renderNotifications(data.notifications);
            })
            .catch(function () { /* silently ignore */ });
    }

    function renderNotifications(items) {
        if (!items.length) return;

        var newItems = items.filter(function (n) { return !seenIds.has(n.id); });
        if (!newItems.length) return;

        // Show badge for unread
        var hasUnread = items.some(function (n) { return !n.read_at; });
        if (badge) badge.hidden = !hasUnread;

        // Clear empty state
        var emptyLi = list.querySelector('.notif-list__empty');
        if (emptyLi) emptyLi.remove();

        // Prepend new notifications
        newItems.reverse().forEach(function (n) {
            seenIds.add(n.id);
            var li = document.createElement('li');
            li.className = 'notif-item';
            li.innerHTML =
                '<p class="notif-item__type notif-item__type--' + escHtml(n.type) + '">' +
                    formatType(n.type) +
                '</p>' +
                '<p class="notif-item__body">' + escHtml(n.message) + '</p>' +
                '<p class="notif-item__time">' + escHtml(n.time_ago) + '</p>';

            list.prepend(li);
        });

        // Keep list max 15 items
        var allItems = list.querySelectorAll('.notif-item');
        allItems.forEach(function (item, i) {
            if (i >= 15) item.remove();
        });
    }

    function formatType(type) {
        if (type === '3_away') return '⚡ Almost your turn!';
        if (type === 'called') return '🔔 You\'re being called!';
        return type;
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
})();
