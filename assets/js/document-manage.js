// assets/js/document-manage.js
(function () {
    'use strict';

    /* ── Document list: AJAX search + refresh ───────────────────
       Scoped to document-list.php. Both actions fetch the same
       endpoint with X-Requested-With: XMLHttpRequest, which makes
       document-list.php return { success, html, count, search }
       instead of a full page, so we can swap #doc-list-results
       in place without a hard reload. ── */
    const resultsEl  = document.getElementById('doc-list-results');
    const searchForm = document.getElementById('doc-search-form');
    const searchInput = document.getElementById('doc-search');
    const refreshBtn  = document.getElementById('refresh-list-btn');
    const statusRegion = document.getElementById('doc-status-region');

    let debounceTimeout;
    function debounce(func, delay) {
        return function(...args) {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    }

    const debouncedLoadDocumentList = debounce(loadDocumentList, 300);


    function announce(message) {
        if (statusRegion) statusRegion.textContent = message;
    }

    function bindDeleteHandlers(scope) {
        scope.querySelectorAll('.delete-document').forEach(function (button) {
            button.addEventListener('click', handleDeleteDocumentClick);
        });
        // "Clear search" link inside the empty-state, injected via AJAX —
        // route it through the same AJAX flow instead of a full reload.
        const clearLink = scope.querySelector('.clear-search-link');
        if (clearLink) {
            clearLink.addEventListener('click', function (e) {
                e.preventDefault();
                if (searchInput) searchInput.value = '';
                loadDocumentList(new URLSearchParams());
            });
        }
    }

    function loadDocumentList(params, opts) {
        opts = opts || {};
        if (!resultsEl) return Promise.resolve();

        resultsEl.classList.add('is-loading');
        resultsEl.setAttribute('aria-busy', 'true');

        const url = 'document-list.php?' + params.toString() +
                    (params.toString() ? '&' : '') + 'ajax=1';

        return fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            resultsEl.innerHTML = data.html;
            bindDeleteHandlers(resultsEl);

            if (opts.pushUrl !== false) {
                const displayParams = new URLSearchParams(params.toString());
                const newUrl = 'document-list.php' + (displayParams.toString() ? '?' + displayParams.toString() : '');
                history.pushState({ search: data.search }, '', newUrl);
            }

            announce(
                data.success
                    ? (data.search
                        ? `${data.count} document type${data.count === 1 ? '' : 's'} found for "${data.search}".`
                        : `Showing all document types.`)
                    : 'Unable to load documents.'
            );
        })
        .catch(() => {
            announce('Network error while loading documents.');
        })
        .finally(() => {
            resultsEl.classList.remove('is-loading');
            resultsEl.removeAttribute('aria-busy');
        });
    }

    if (searchForm && resultsEl) {
        searchInput.addEventListener('input', function (e) {
            e.preventDefault();
            const params = new URLSearchParams();
            const term = (searchInput && searchInput.value.trim()) || '';
            if (term !== '') params.set('search', term);
            debouncedLoadDocumentList(params);
        });
    }

    if (refreshBtn && resultsEl) {
        refreshBtn.addEventListener('click', function () {
            const orig = refreshBtn.innerHTML;
            refreshBtn.disabled = true;

            const params = new URLSearchParams(window.location.search);
            // Keep whatever's currently in the search box, in case the
            // user typed something without submitting yet.
            if (searchInput && searchInput.value.trim() !== '') {
                params.set('search', searchInput.value.trim());
            }

            loadDocumentList(params, { pushUrl: false }).finally(() => {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = orig;
            });
        });
    }

    // Back/forward navigation: re-sync the list with the URL's search param.
    window.addEventListener('popstate', function () {
        if (!resultsEl) return;
        const params = new URLSearchParams(window.location.search);
        if (searchInput) searchInput.value = params.get('search') || '';
        loadDocumentList(params, { pushUrl: false });
    });

    /* ── Delete document ─────────────────────────────────────── */
    function handleDeleteDocumentClick() {
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
    }

    document.querySelectorAll('.delete-document').forEach(function (button) {
        button.addEventListener('click', handleDeleteDocumentClick);
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