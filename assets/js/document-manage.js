// assets/js/document-manage.js
(function () {
    'use strict';

    /* ── Delete document ─────────────────────────────────────── */
    document.querySelectorAll('.delete-document').forEach(function (button) {
        button.addEventListener('click', function () {
            const documentId = this.dataset.id;

            if (!confirm('Are you sure you want to delete this document type? This will also delete all associated requirements. This action cannot be undone.')) {
                return;
            }

            const orig = this.textContent;
            this.disabled = true;
            this.textContent = '…';

            fetch('/admin/document/document-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${documentId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Remove the row with a fade
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(8px)';
                        setTimeout(() => row.remove(), 320);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(data.message);
                    this.disabled = false;
                    this.textContent = orig;
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                this.disabled = false;
                this.textContent = orig;
            });
        });
    });

    /* ── Delete requirement ──────────────────────────────────── */
    document.querySelectorAll('.btn-delete-req').forEach(function (button) {
        button.addEventListener('click', function () {
            const reqId = this.dataset.id;

            if (!confirm('Delete this requirement? This cannot be undone.')) return;

            const orig = this.textContent;
            this.disabled = true;
            this.textContent = '…';

            fetch('/admin/requirements/requirements-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${reqId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(8px)';
                        setTimeout(() => row.remove(), 320);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(data.message || 'Could not delete requirement.');
                    this.disabled = false;
                    this.textContent = orig;
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                this.disabled = false;
                this.textContent = orig;
            });
        });
    });

})();