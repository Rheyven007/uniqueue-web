// assets/js/office-manage.js
(function () {
    'use strict';

    document.querySelectorAll('.btn-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const officeId = this.dataset.id;
            const currentIsActive = this.dataset.is_active; // '0' or '1'
            const newIsActive = currentIsActive === '1' ? '0' : '1';
            const actionText = newIsActive === '1' ? 'Activate' : 'Deactivate';

            if (!confirm(`Are you sure you want to ${actionText} this office?`)) {
                return;
            }

            fetch('/admin/office/office-toggle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${officeId}&is_active=${currentIsActive}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload(); // Simple reload for now, can update UI dynamically
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
})();