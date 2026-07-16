// assets/js/dashboard.js — Live updates for the student dashboard

(function () {
    'use strict';

    /* ── DOM References ────────────────────────────────────────────────────── */
    const activeTicketWidget = document.getElementById('active-ticket-widget');
    const totalWaitingEl = document.getElementById('stat-total-waiting');
    const estWaitEl = document.getElementById('stat-est-wait');

    /* ── Constants ─────────────────────────────────────────────────────────── */
    const TICKET_POLL_INTERVAL_SECONDS = 10;
    const STATS_POLL_INTERVAL_SECONDS = 15;

    /* ── Active Ticket Polling ─────────────────────────────────────────────── */
    function initializeTicketPolling() {
        if (!activeTicketWidget) {
            return; // No active ticket on the page, do nothing.
        }
        const ticketId = activeTicketWidget.dataset.ticketId;
        if (!ticketId) {
            return;
        }

        // Initial check and start polling
        pollTicketStatus(ticketId);
        setInterval(() => pollTicketStatus(ticketId), TICKET_POLL_INTERVAL_SECONDS * 1000);
    }
    function pollTicketStatus(ticketId) {
        fetch(`/api/get-queue-status.php?ticket_id=${encodeURIComponent(ticketId)}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (!data.success) {
                    // If the ticket is not found, it might have been cleared. Remove the widget.
                    removeActiveTicketSection();
                    return;
                }

                // If ticket is completed or cancelled, remove the section from the dashboard.
                if (data.status === 'done' || data.status === 'cancelled' || data.status === 'completed') {
                    removeActiveTicketSection();
                    return;
                }

                // Update the status badge on the dashboard
                const statusBadge = activeTicketWidget.querySelector('.ticket-status-badge');
                if (statusBadge) {
                    const formattedStatus = (data.status || '').replace('_', ' ');
                    statusBadge.textContent = formattedStatus.charAt(0).toUpperCase() + formattedStatus.slice(1);
                    statusBadge.className = `ticket-status-badge ticket-status-badge--${data.status}`;
                }
            })
            .catch(err => {
                console.error('Failed to poll ticket status:', err);
            });
    }

    function removeActiveTicketSection() {
        const section = document.getElementById('active-ticket-section');
        if (section) {
            section.style.transition = 'opacity 0.5s ease, transform 0.5s ease, max-height 0.5s ease';
            section.style.opacity = '0';
            section.style.transform = 'scale(0.95)';
            section.style.maxHeight = '0';
            setTimeout(() => section.remove(), 500); // Remove from DOM after transition
        }
    }

    /* ── Dashboard Stats Polling ───────────────────────────────────────────── */
    function pollDashboardStats() {
        fetch('/api/get-dashboard-stats.php')
            .then(res => {
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (!data.success) return;

                updateStat(totalWaitingEl, data.total_waiting);

                if (estWaitEl) {
                    if (data.est_wait_mins !== null) {
                        estWaitEl.innerHTML = `${data.est_wait_mins}<span class="hero-stat__unit">min</span>`;
                    } else {
                        estWaitEl.innerHTML = '&mdash;';
                    }
                    updateStat(estWaitEl, data.est_wait_mins, true); // Pass true to skip number check
                }
            })
            .catch(err => console.error('Failed to poll dashboard stats:', err));
    }

    function updateStat(el, newValue, isHtml = false) {
        if (!el) return;
        const oldValue = isHtml ? el.innerHTML : el.textContent;
        if (oldValue != newValue) {
            el.style.opacity = '0.5';
            setTimeout(() => {
                if (!isHtml) el.textContent = newValue;
                el.style.transition = 'opacity 0.3s ease';
                el.style.opacity = '1';
            }, 150);
        }
    }

    // Initialize all polling
    initializeTicketPolling();
    pollDashboardStats(); // Initial call
    setInterval(pollDashboardStats, STATS_POLL_INTERVAL_SECONDS * 1000);
})();