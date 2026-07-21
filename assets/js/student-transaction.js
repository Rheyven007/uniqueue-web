/* ============================================================
   Uniqueue — student-transaction.js
   Read-only transaction detail modal.
   Clicking a transaction card opens a modal showing its full
   details. Nothing here is editable — it only displays data
   already rendered by the server in the card's data-txn attribute.
   Footer action links (Track Queue / Rate Experience) are excluded
   from the click so they keep navigating normally.
   ============================================================ */
(function () {
    var list  = document.getElementById('txnList');
    var modal = document.getElementById('txnModal');
    if (!list || !modal) return;

    var els = {
        title:       document.getElementById('txnModalTitle'),
        status:      document.getElementById('txnModalStatus'),
        badges:      document.getElementById('txnModalBadges'),
        office:      document.getElementById('txnModalOffice'),
        windowRow:   document.getElementById('txnModalWindowRow'),
        window:      document.getElementById('txnModalWindow'),
        docsRow:     document.getElementById('txnModalDocsRow'),
        docs:        document.getElementById('txnModalDocs'),
        joined:      document.getElementById('txnModalJoined'),
        calledRow:   document.getElementById('txnModalCalledRow'),
        called:      document.getElementById('txnModalCalled'),
        doneRow:     document.getElementById('txnModalDoneRow'),
        doneLabel:   document.getElementById('txnModalDoneLabel'),
        done:        document.getElementById('txnModalDone'),
        feedbackSec: document.getElementById('txnModalFeedbackSection'),
        rating:      document.getElementById('txnModalRating'),
        comment:     document.getElementById('txnModalComment'),
        panel:       modal.querySelector('.txn-modal__panel')
    };

    var lastFocused = null;

    function setRow(rowEl, valueEl, value) {
        if (value === null || value === undefined || value === '') {
            rowEl.hidden = true;
            return;
        }
        rowEl.hidden = false;
        valueEl.textContent = value;
    }

    function openModal(data) {
        els.title.textContent = '#' + data.queue_number;

        els.status.textContent = data.status;
        els.status.className = 'ticket-status-badge ticket-status-badge--' + data.status_raw;

        els.badges.innerHTML = '';
        var typeBadge = document.createElement('span');
        typeBadge.className = 'ticket-type-badge ticket-type-badge--' + (data.type === 'Appointment' ? 'appointment' : 'walkin');
        typeBadge.textContent = data.type;
        els.badges.appendChild(typeBadge);

        if (data.priority) {
            var priorityBadge = document.createElement('span');
            priorityBadge.className = 'ticket-type-badge ticket-type-badge--priority';
            priorityBadge.textContent = 'Priority';
            els.badges.appendChild(priorityBadge);
        }

        els.office.textContent = data.office_name;
        setRow(els.windowRow, els.window, data.window_name);
        setRow(els.docsRow, els.docs, data.documents);
        els.joined.textContent = data.joined_at;
        setRow(els.calledRow, els.called, data.called_at);

        if (data.status_raw === 'cancelled') {
            els.doneLabel.textContent = 'Cancelled';
            els.doneRow.classList.add('txn-modal__timeline-item--cancelled');
            setRow(els.doneRow, els.done, data.done_at || 'Ticket was cancelled');
        } else {
            els.doneLabel.textContent = 'Done';
            els.doneRow.classList.remove('txn-modal__timeline-item--cancelled');
            setRow(els.doneRow, els.done, data.done_at);
        }

        if (data.feedback_rating) {
            els.feedbackSec.hidden = false;
            els.rating.textContent = '\u2605'.repeat(data.feedback_rating) + '\u2606'.repeat(5 - data.feedback_rating);
            if (data.feedback_comment) {
                els.comment.hidden = false;
                els.comment.textContent = data.feedback_comment;
            } else {
                els.comment.hidden = true;
            }
        } else {
            els.feedbackSec.hidden = true;
        }

        lastFocused = document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        els.panel.focus();
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocused) lastFocused.focus();
    }

    list.addEventListener('click', function (e) {
        if (e.target.closest('[data-no-modal]')) return;
        var card = e.target.closest('.transaction-card--clickable');
        if (!card) return;
        try {
            openModal(JSON.parse(card.getAttribute('data-txn')));
        } catch (err) {
            /* malformed payload — fail silently, no modal */
        }
    });

    list.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('[data-no-modal]')) return;
        var card = e.target.closest('.transaction-card--clickable');
        if (!card) return;
        e.preventDefault();
        try {
            openModal(JSON.parse(card.getAttribute('data-txn')));
        } catch (err) {
            /* malformed payload — fail silently, no modal */
        }
    });

    modal.addEventListener('click', function (e) {
        if (e.target.closest('[data-txn-close]')) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();