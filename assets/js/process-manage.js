// assets/js/process-manage.js
(function () {
    'use strict';

    document.querySelectorAll('.delete-process').forEach(function (button) {
        button.addEventListener('click', function () {
            const processId = this.dataset.id;

            if (!confirm('Are you sure you want to delete this process? This action cannot be undone.')) {
                return;
            }

            fetch('/admin/process/process-delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${processId}`
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