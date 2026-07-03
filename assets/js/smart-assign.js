// assets/js/smart-assign.js — Handles the "Call Next" interaction for staff windows

(function () {
    'use strict';

    const callNextBtn = document.getElementById('call-next-btn');
    const currentTicketDisplay = document.getElementById('current-ticket-info');

    if (!callNextBtn) return;

    callNextBtn.addEventListener('click', function () {
        const windowId = this.dataset.windowId;

        if (!windowId) {
            alert('Error: Window configuration missing.');
            return;
        }

        // Show loading state
        callNextBtn.disabled = true;
        callNextBtn.textContent = 'Calling...';

        const formData = new FormData();
        formData.append('window_id', windowId);

        fetch('/api/assign-counter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.ticket) {
                updateTicketDisplay(data.ticket);
                // Optional: Trigger a notification or voice announcement system
                console.log('Called Ticket:', data.ticket.queue_number);
            } else {
                alert(data.message || 'No students currently in queue.');
            }
        })
        .catch(error => {
            console.error('Error calling next student:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
            callNextBtn.disabled = false;
            callNextBtn.textContent = 'Call Next Student';
        });
    });

    function updateTicketDisplay(ticket) {
        if (currentTicketDisplay) {
            currentTicketDisplay.innerHTML = `<strong>${ticket.queue_number}</strong> - ${ticket.first_name} ${ticket.last_name} (${ticket.sr_code})`;
        }
    }
})();