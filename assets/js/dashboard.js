// assets/js/dashboard.js — Live updates for the student dashboard

(function () {
    'use strict';

    /* ── DOM References ────────────────────────────────────────────────────── */
    const activeTicketWidget = document.getElementById('active-ticket-widget');
    const trackQueueBtn = document.getElementById('trackQueueBtn');
    const queueModal = document.getElementById('queueStatusModal');
    const closeQueueModal = document.getElementById('closeQueueModal');
    const modalBackdrop = queueModal?.querySelector('.queue-modal__backdrop');
    const refreshQueueBtn = document.getElementById('refreshQueueStatus');

let modalPoller = null;

    /* ── Constants ─────────────────────────────────────────────────────────── */
    const TICKET_POLL_INTERVAL_SECONDS = 1;
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

        console.log("Polling...", new Date().toLocaleTimeString());

        fetch(`/api/get-queue-status.php?ticket_id=${encodeURIComponent(ticketId)}&_=${Date.now()}`, {
            cache: "no-store"
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                console.log("Queue Status:", data);
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
                const statusBadge = document.getElementById('dashboard-status-badge');
                console.log("Status badge:", statusBadge);
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

    /* ─────────────────────────────────────────────────────────────
   Queue Status Modal
───────────────────────────────────────────────────────────── */

function openQueueModal(ticketId) {

    if (!queueModal) return;

    queueModal.classList.add('show');
    document.body.style.overflow = 'hidden';

    loadQueueStatus(ticketId);

    if (modalPoller) {
        clearInterval(modalPoller);
    }

    modalPoller = setInterval(() => {
        loadQueueStatus(ticketId);
    }, 1000);
}

function closeQueueStatusModal() {

    if (!queueModal) return;

    queueModal.classList.remove('show');
    document.body.style.overflow = '';

    if (modalPoller) {
        clearInterval(modalPoller);
        modalPoller = null;
    }
}

function loadQueueStatus(ticketId) {

    fetch(`/api/get-queue-status.php?ticket_id=${encodeURIComponent(ticketId)}`)
        .then(r => r.json())
        .then(data => {

            if (!data.success) {
                closeQueueStatusModal();
                return;
            }

            document.getElementById('office-name').textContent =
                data.office_name || '';

            document.getElementById('queue-number').textContent =
                data.queue_number || '';

            document.getElementById('people-ahead').textContent =
                data.people_ahead ?? 0;

            document.getElementById('ewt').textContent =
                data.estimated_wait ?? '--';

            document.getElementById('assigned-window-name').textContent =
                data.window_name || 'Pending';

            document.getElementById('window-name').textContent =
                data.window_name || '';

            document.getElementById('last-updated').textContent =
                new Date().toLocaleTimeString();

            const badge = document.getElementById('status-badge');

            badge.textContent =
                (data.status || '')
                    .replace('_', ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());

            badge.className =
                'ticket-status-badge ticket-status-badge--' + data.status;

            const waiting =
                document.getElementById('waiting-info');

            const called =
                document.getElementById('called-info');

            if (
                data.status === 'called' ||
                data.status === 'in_progress'
            ) {

                waiting.classList.add('hidden');
                called.classList.remove('hidden');

            } else {

                waiting.classList.remove('hidden');
                called.classList.add('hidden');
            }

            if (
                data.status === 'done' ||
                data.status === 'completed' ||
                data.status === 'cancelled'
            ) {

                closeQueueStatusModal();

                location.reload();
            }

        })
        .catch(console.error);
}

        if (trackQueueBtn) {
            trackQueueBtn.addEventListener('click', function () {
                openQueueModal(this.dataset.ticketId);
            });
        }
        if (closeQueueModal) {
            closeQueueModal.addEventListener('click', closeQueueStatusModal);
        }
        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', closeQueueStatusModal);
        }
        if (refreshQueueBtn) {
            refreshQueueBtn.addEventListener('click', function () {
                if (trackQueueBtn) {
                    loadQueueStatus(trackQueueBtn.dataset.ticketId);
                }
            });
        }
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
                closeQueueStatusModal();
            }

        });
    // Initialize all polling
    initializeTicketPolling();
    pollDashboardStats(); // Initial call
    setInterval(pollDashboardStats, STATS_POLL_INTERVAL_SECONDS * 1000);
})();