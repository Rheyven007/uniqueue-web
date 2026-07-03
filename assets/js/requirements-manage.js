// assets/js/requirements.js — Document Requirements pages (list / add / edit)
(function () {
    'use strict';

    // ── DELETE handler (list page) ─────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-req').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (!confirm('Delete this requirement? This cannot be undone.')) return;

            const fd = new FormData();
            fd.append('requirement_id', id);

            fetch('/api/delete-requirement.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Remove row from DOM without a full reload
                        const row = this.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.25s, transform 0.25s';
                            row.style.opacity    = '0';
                            row.style.transform  = 'translateX(12px)';
                            setTimeout(() => row.remove(), 260);
                        }
                    } else {
                        alert(data.message || 'Delete failed. Please try again.');
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        });
    });

    // ── Dynamic rows (add page) ────────────────────────────────────────────────
    const listInput = document.getElementById('requirements-list-input');

    if (listInput) {
        window.renumber = function () {
            listInput.querySelectorAll('.req-row').forEach((row, i) => {
                row.dataset.index = i;
                row.querySelector('.req-num').textContent = i + 1;
            });
        };

        window.addRow = function () {
            const rows  = listInput.querySelectorAll('.req-row');
            const count = rows.length;
            const div   = document.createElement('div');
            div.className      = 'req-row';
            div.dataset.index  = count;
            div.innerHTML = `
                <span class="req-num">${count + 1}</span>
                <input type="text" name="requirements[]" class="form-control req-input"
                       placeholder="e.g. 2x2 ID photo">
                <button type="button" class="btn-remove-req" title="Remove" onclick="removeRow(this)">&times;</button>
            `;
            listInput.appendChild(div);
            div.querySelector('input').focus();
        };

        window.removeRow = function (btn) {
            const rows = listInput.querySelectorAll('.req-row');
            if (rows.length <= 1) return; // keep at least one row
            btn.closest('.req-row').remove();
            renumber();
        };
    }

})();