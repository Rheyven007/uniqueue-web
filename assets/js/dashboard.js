// assets/js/dashboard.js — Student dashboard dynamic content

(function () {
    'use strict';

    // ── Highlight active nav link ──────────────────────────────────────────────
    var currentPath = window.location.pathname;
    document.querySelectorAll('.site-nav__link').forEach(function (link) {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ── Auto-refresh active ticket status every 30 seconds ───────────────────
    var ticketWidget = document.getElementById('active-ticket-widget');
    if (ticketWidget) {
        var ticketId = ticketWidget.dataset.ticketId;
        if (ticketId) {
            setInterval(function () {
                refreshTicketStatus(ticketId);
            }, 30000);
        }
    }

    function refreshTicketStatus(ticketId) {
        fetch('/api/get-queue-status.php?ticket_id=' + encodeURIComponent(ticketId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || data.error) return;

                var statusBadge = ticketWidget.querySelector('.ticket-status-badge');
                if (statusBadge && data.status) {
                    statusBadge.className = 'ticket-status-badge ticket-status-badge--' + data.status;
                    statusBadge.textContent = formatStatus(data.status);
                }

                if (data.status === 'done' || data.status === 'cancelled') {
                    window.location.reload();
                }
            })
            .catch(function () { /* silently ignore */ });
    }

    function formatStatus(status) {
        var map = {
            waiting:     'Waiting',
            called:      'Called',
            in_progress: 'In Progress',
            done:        'Done',
            cancelled:   'Cancelled',
        };
        return map[status] || status;
    }

    // ── Animate office cards on load ──────────────────────────────────────────
    var officeCards = document.querySelectorAll('.office-card');
    officeCards.forEach(function (card, i) {
        card.style.opacity = '0';
        card.style.animationDelay = (i * 0.06) + 's';
        card.style.animation = 'fadeSlideUp 0.4s ease both';
    });

    // ── Notification bell toggle ──────────────────────────────────────────────
    var bell     = document.getElementById('notif-bell');
    var dropdown = document.getElementById('notif-dropdown');
    var closeBtn = document.getElementById('notif-close');

    if (bell && dropdown) {
        bell.addEventListener('click', function () {
            var expanded = bell.getAttribute('aria-expanded') === 'true';
            bell.setAttribute('aria-expanded', String(!expanded));
            dropdown.hidden = expanded;
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                bell.setAttribute('aria-expanded', 'false');
                dropdown.hidden = true;
            });
        }

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                bell.setAttribute('aria-expanded', 'false');
                dropdown.hidden = true;
            }
        });
    }

})();