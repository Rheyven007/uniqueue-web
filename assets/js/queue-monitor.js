// assets/js/queue-monitor.js — Real-time queue status polling

(function () {
    'use strict';

    const container = document.getElementById('queue-status-container');
    if (!container) return;

    const ticketId = container.dataset.ticketId;
    const officeNameEl = document.getElementById('office-name');
    const statusBadge = document.getElementById('status-badge');
    const queueNumberEl = document.getElementById('queue-number');
    const peopleAheadEl = document.getElementById('people-ahead');
    const ewtEl = document.getElementById('ewt');
    const windowNameEl = document.getElementById('window-name');
    const assignedWindowNameEl = document.getElementById('assigned-window-name');
    const waitingInfo = document.getElementById('waiting-info');
    const calledInfo = document.getElementById('called-info');
    const lastUpdatedEl = document.getElementById('last-updated');

    function updateStatus() {
        fetch(`/api/get-queue-status.php?ticket_id=${encodeURIComponent(ticketId)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                officeNameEl.textContent = data.office_name;
                queueNumberEl.textContent = data.queue_number;
                statusBadge.textContent = formatStatus(data.status);
                statusBadge.className = `ticket-status-badge ticket-status-badge--${data.status}`;
                lastUpdatedEl.textContent = new Date().toLocaleTimeString();

                if (data.status === 'called' || data.status === 'in_progress') {
                    waitingInfo.classList.add('hidden');
                    calledInfo.classList.remove('hidden');
                    windowNameEl.textContent = data.window_name || 'Assigned Window';
                } else if (data.status === 'waiting') {
                    waitingInfo.classList.remove('hidden');
                    calledInfo.classList.add('hidden');
                    peopleAheadEl.textContent = data.people_ahead;
                    ewtEl.textContent = data.ewt;
                    if (assignedWindowNameEl) {
                        assignedWindowNameEl.textContent = data.window_name || 'Unassigned';
                    }
                } else if (data.status === 'done' || data.status === 'cancelled') {
                    // Auto-redirect to dashboard when transaction ends
                    window.location.href = '/student/dashboard.php';
                }
            })
            .catch(err => console.error('Status monitoring failed:', err));
    }

    function formatStatus(status) {
        const map = {
            waiting: 'Waiting in Queue',
            called: 'Called to Window',
            in_progress: 'Processing...',
            done: 'Completed',
            cancelled: 'Cancelled'
        };
        return map[status] || status;
    }

    // Refresh data every 10 seconds for high responsiveness on the dedicated page
    updateStatus();
    setInterval(updateStatus, 10000);
})();