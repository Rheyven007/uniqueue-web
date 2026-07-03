// assets/js/office-dashboard.js — Office dashboard live refresh & interactions
(function () {
    'use strict';

    /* ── DOM refs ──────────────────────────────────────────────────────────── */
    const waitingListEl    = document.getElementById('waiting-queue-list');
    const inProgressListEl = document.getElementById('in-progress-queue-list');
    const waitingCount     = document.getElementById('waiting-count');
    const servingCount     = document.getElementById('serving-count');
    const smartAssignBtn   = document.getElementById('smart-assign-btn');

    if (!waitingListEl || !inProgressListEl) return;
    if (typeof CURRENT_OFFICE_ID === 'undefined') return;

    const REFRESH_INTERVAL = 15; // seconds

    /* ── Toast ─────────────────────────────────────────────────────────────── */
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        document.body.appendChild(toastContainer);
    }

    function showToast(msg, type = '') {
        const el = document.createElement('div');
        el.className = 'toast' + (type ? ' toast-' + type : '');

        // icon
        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        el.innerHTML = `<span>${icon}</span><span>${esc(msg)}</span>`;
        toastContainer.appendChild(el);

        setTimeout(() => {
            el.style.animation = 'toastOut 0.3s ease forwards';
            el.addEventListener('animationend', () => el.remove());
        }, 3200);
    }

    /* ── Auto-refresh ring ─────────────────────────────────────────────────── */
    const CIRCUMFERENCE = 2 * Math.PI * 18; // ~113

    const ring = document.createElement('button');
    ring.className = 'refresh-ring';
    ring.title = 'Click to refresh now';
    ring.setAttribute('aria-label', 'Auto-refresh countdown');
    ring.innerHTML = `
        <svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg">
          <circle class="refresh-ring__track" cx="21" cy="21" r="18"/>
          <circle class="refresh-ring__fill"  cx="21" cy="21" r="18"/>
          <text x="21" y="25.5" text-anchor="middle"
                font-family="DM Sans,sans-serif" font-size="10" font-weight="600"
                fill="var(--ink-mid)" id="ring-label">15</text>
        </svg>`;
    document.body.appendChild(ring);

    const ringFill  = ring.querySelector('.refresh-ring__fill');
    const ringLabel = ring.querySelector('#ring-label');
    let countdown   = REFRESH_INTERVAL;

    function updateRing() {
        const pct    = countdown / REFRESH_INTERVAL;
        const offset = CIRCUMFERENCE * (1 - pct);
        ringFill.style.strokeDashoffset = offset;
        if (ringLabel) ringLabel.textContent = countdown;
    }

    ring.addEventListener('click', () => {
        fetchDashboardData();
        countdown = REFRESH_INTERVAL;
        updateRing();
    });

    /* ── Fetch & render ────────────────────────────────────────────────────── */
    function fetchDashboardData(silent = true) {
        if (!silent) {
            waitingListEl.innerHTML    = skeletonRows(3);
            inProgressListEl.innerHTML = skeletonRows(2);
        }

        fetch(`/api/get-counters.php?office_id=${CURRENT_OFFICE_ID}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.error('Dashboard fetch failed:', data.message);
                    return;
                }
                renderWaitingQueue(data.waiting_queue     ?? []);
                renderInProgressQueue(data.in_progress_queue ?? []);
            })
            .catch(err => {
                console.error('Dashboard network error:', err);
            });
    }

    function skeletonRows(n) {
        return Array.from({ length: n }, (_, i) => `
            <div class="queue-row" style="animation-delay:${i * 0.06}s">
                <div class="skeleton" style="width:52px;height:22px;border-radius:4px;"></div>
                <div style="flex:1;display:flex;flex-direction:column;gap:6px">
                    <div class="skeleton" style="width:140px;height:14px;border-radius:4px;"></div>
                    <div class="skeleton" style="width:100px;height:11px;border-radius:4px;"></div>
                </div>
            </div>
        `).join('');
    }

    /* ── Waiting queue ─────────────────────────────────────────────────────── */
    function renderWaitingQueue(tickets) {
        updateBadge(waitingCount, tickets.length, 'amber');

        if (!tickets.length) {
            waitingListEl.innerHTML = emptyState('No students waiting.');
            return;
        }

        waitingListEl.innerHTML = tickets.map((t, i) => `
            <div class="queue-row" style="animation-delay:${i * 0.04}s">
                <div class="queue-row__num">${esc(t.queue_number)}</div>
                <div class="queue-row__info">
                    <span class="queue-row__name">${esc(t.first_name)} ${esc(t.last_name)}</span>
                    <span class="queue-row__sub">${esc(t.sr_code)} &nbsp;·&nbsp; ${formatTime(t.joined_at)}</span>
                </div>
                <div class="queue-row__badges">
                    <span class="badge badge-type">${esc(t.type)}</span>
                    ${t.priority ? '<span class="badge badge-priority">Priority</span>' : ''}
                </div>
            </div>
        `).join('');
    }

    /* ── In-progress queue ─────────────────────────────────────────────────── */
    function renderInProgressQueue(tickets) {
        updateBadge(servingCount, tickets.length, 'teal');

        if (!tickets.length) {
            inProgressListEl.innerHTML = emptyState('No tickets currently being served.');
            return;
        }

        inProgressListEl.innerHTML = tickets.map((t, i) => `
            <div class="queue-row queue-row--serving" style="animation-delay:${i * 0.04}s">
                <div class="queue-row__num serving">${esc(t.queue_number)}</div>
                <div class="queue-row__info">
                    <span class="queue-row__name">${esc(t.first_name)} ${esc(t.last_name)}</span>
                    <span class="queue-row__sub">${esc(t.sr_code)} &nbsp;·&nbsp; Called: ${formatTime(t.called_at)}</span>
                </div>
                <div class="queue-row__badges">
                    <span class="badge badge-type">${esc(t.type)}</span>
                    <span class="badge badge-window">Window: ${esc(t.window_name ?? '—')}</span>
                </div>
                <div class="queue-row__actions">
                    <button class="btn btn-xs btn-success btn-ticket-action"
                        data-id="${t.id}" data-action="complete">Done</button>
                    <button class="btn btn-xs btn-ghost btn-ticket-action"
                        data-id="${t.id}" data-action="skip">Skip</button>
                    <button class="btn btn-xs btn-danger btn-ticket-action"
                        data-id="${t.id}" data-action="cancel">Cancel</button>
                </div>
            </div>
        `).join('');

        inProgressListEl.querySelectorAll('.btn-ticket-action').forEach(btn => {
            btn.addEventListener('click', handleTicketAction);
        });
    }

    /* ── Update badge with pop animation ──────────────────────────────────── */
    function updateBadge(el, count, cls) {
        if (!el) return;
        const prev = parseInt(el.textContent, 10);
        el.textContent = count;
        if (!isNaN(prev) && prev !== count) {
            el.classList.remove('pop');
            void el.offsetWidth; // reflow
            el.classList.add('pop');
        }
    }

    /* ── Empty state HTML ──────────────────────────────────────────────────── */
    function emptyState(msg) {
        return `<div class="empty-state">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
            </svg>
            ${esc(msg)}
        </div>`;
    }

    /* ── Ticket actions ────────────────────────────────────────────────────── */
    function handleTicketAction() {
        const btn    = this;
        const id     = btn.dataset.id;
        const action = btn.dataset.action;

        const labels = { complete: 'Mark as done', skip: 'Skip', cancel: 'Cancel' };
        if (!confirm(`${labels[action] || capitalize(action)} this ticket?`)) return;

        btn.disabled    = true;
        btn.textContent = '…';

        const fd = new FormData();
        fd.append('ticket_id', id);
        fd.append('action', action);

        fetch('/api/update-queue-status.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const msgs = { complete: 'Ticket completed.', skip: 'Ticket skipped.', cancel: 'Ticket cancelled.' };
                    showToast(msgs[action] || 'Updated.', 'success');
                    fetchDashboardData();
                } else {
                    showToast(data.message || 'Action failed.', 'error');
                    btn.disabled    = false;
                    btn.textContent = capitalize(action);
                }
            })
            .catch(() => {
                showToast('Network error. Please try again.', 'error');
                btn.disabled    = false;
                btn.textContent = capitalize(action);
            });
    }

    /* ── Window open/close toggle ──────────────────────────────────────────── */
    document.querySelectorAll('.btn-toggle-counter').forEach(btn => {
        btn.addEventListener('click', function () {
            const windowId      = this.dataset.id;
            const currentStatus = this.dataset.status; // 'open' | 'closed'
            const label         = currentStatus === 'open' ? 'Close' : 'Open';

            if (!confirm(`${label} this window?`)) return;

            const orig = this.textContent;
            this.disabled    = true;
            this.textContent = '…';

            const fd = new FormData();
            fd.append('id',     windowId);       // counter-toggle.php expects 'id'
            fd.append('status', currentStatus);  // counter-toggle.php expects CURRENT status

            fetch('/admin/counter/counter-toggle.php', { method: 'POST', credentials: 'same-origin', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const newStatus = data.new_status;
                        showToast(`Window ${newStatus === 'open' ? 'opened' : 'closed'}.`, 'success');

                        // Update button without full reload
                        this.dataset.status  = newStatus;
                        this.textContent     = newStatus === 'open' ? 'Close Window' : 'Open Window';
                        this.disabled        = false;

                        // Update window card visuals
                        const card = this.closest('.window-card');
                        if (card) {
                            card.classList.remove('is-open', 'is-closed');
                            card.classList.add(`is-${newStatus}`);

                            const dot = card.querySelector('.status-dot');
                            if (dot) {
                                dot.classList.remove('open', 'closed');
                                dot.classList.add(newStatus);
                                dot.title = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                            }

                            const metaStrong = card.querySelector('.window-card__meta strong');
                            if (metaStrong) {
                                metaStrong.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                                metaStrong.style.color = newStatus === 'open' ? 'var(--green)' : 'var(--muted)';
                            }

                            const emptySlot = card.querySelector('.empty-slot');
                            if (emptySlot) {
                                emptySlot.textContent = newStatus === 'open' ? 'Idle — ready for next' : 'Window closed';
                            }
                        }
                    } else {
                        showToast(data.message || 'Failed to update window.', 'error');
                        this.disabled    = false;
                        this.textContent = orig;
                    }
                })
                .catch(() => {
                    showToast('Network error. Please try again.', 'error');
                    this.disabled    = false;
                    this.textContent = orig;
                });
        });
    });

    /* ── Smart assign ──────────────────────────────────────────────────────── */
    if (smartAssignBtn) {
        smartAssignBtn.addEventListener('click', function () {
            this.disabled    = true;
            this.textContent = 'Assigning…';

            fetch('/api/smart-assign.php', {
                method: 'POST',
                body: new URLSearchParams({ office_id: CURRENT_OFFICE_ID })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Smart assign complete.', 'success');
                        fetchDashboardData();
                    } else {
                        showToast(data.message || 'Smart assign failed.', 'error');
                    }
                })
                .catch(() => showToast('Network error.', 'error'))
                .finally(() => {
                    this.disabled    = false;
                    this.textContent = 'Smart Assign';
                });
        });
    }

    /* ── Helpers ───────────────────────────────────────────────────────────── */
    function esc(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatTime(dt) {
        if (!dt) return '—';
        return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function capitalize(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    /* ── Boot ──────────────────────────────────────────────────────────────── */
    // Initial load with skeleton
    fetchDashboardData(false);
    updateRing();

    // Countdown ticker + periodic refresh
    const ticker = setInterval(() => {
        countdown--;
        updateRing();

        if (countdown <= 0) {
            fetchDashboardData();
            countdown = REFRESH_INTERVAL;
        }
    }, 1000);

    // Clean up on page hide
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(ticker);
        }
    });

})();