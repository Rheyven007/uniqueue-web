// admin/queue/queue-transaction.php — filter interactions
(function () {
    var form   = document.getElementById('qtFilterForm');
    var search = document.getElementById('qtSearch');
    var status = document.getElementById('qtStatus');
    var type   = document.getElementById('qtType');
    var from   = document.getElementById('qtFrom');
    var to     = document.getElementById('qtTo');

    if (!form) return;

    // Auto-submit immediately when a dropdown/date changes.
    [status, type, from, to].forEach(function (el) {
        if (el) el.addEventListener('change', function () { form.submit(); });
    });

    // Debounce the free-text search so it doesn't reload on every keystroke.
    if (search) {
        var debounceTimer;
        search.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { form.submit(); }, 500);
        });
    }

    // Expand/collapse extra document tags per row.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.qt-doc-toggle');
        if (!btn) return;

        var list = btn.closest('.qt-doc-list');
        var expanded = list.classList.toggle('is-expanded');
        btn.textContent = expanded ? btn.dataset.less : btn.dataset.more;
    });
})();