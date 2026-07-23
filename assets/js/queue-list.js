// assets/js/queue-list.js
(function () {
    'use strict';

    const tabsContainer = document.querySelector('.ql-tabs');
    const windowSelect   = document.getElementById('window-select');
    const contentArea    = document.getElementById('ql-content-area');
    const refreshBtn     = document.querySelector('.od-topbar__actions button');
    const skeletonTemplate = document.getElementById('skeleton-loader-template');

    if (!tabsContainer || !windowSelect || !contentArea) {
        return;
    }

    function getCurrentFilters() {
        const activeTab = tabsContainer.querySelector('.ql-tab.is-active');
        const type = activeTab ? new URL(activeTab.href).searchParams.get('type') : 'all';
        return { type, window: windowSelect.value };
    }

    function updateView(type, windowFilter, pushState, triggerBtn) {
        pushState = pushState !== false;

        const url = new URL(window.location.href);
        url.searchParams.set('type', type);
        url.searchParams.set('window', windowFilter);

        // Loading state — same class-based pattern used on the other list
        // pages (document/staff/counter), instead of inline style tweaks.
        if (skeletonTemplate) {
            contentArea.innerHTML = skeletonTemplate.innerHTML;
        }
        contentArea.classList.add('is-loading');
        contentArea.setAttribute('aria-busy', 'true');
        if (triggerBtn) {
            triggerBtn.classList.add('is-loading');
            triggerBtn.disabled = true;
        }

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                contentArea.innerHTML = '<div class="empty-state empty-state--error"><p>Could not load queue data.</p></div>';
                return;
            }

            contentArea.innerHTML = data.contentHtml;

            document.querySelector('[data-count="total"]').textContent = data.counts.total;
            document.querySelector('[data-count="walkin"]').textContent = data.counts.walkin;
            document.querySelector('[data-count="appointment"]').textContent = data.counts.appointment;

            if (pushState) {
                history.pushState({ type, window: windowFilter }, '', url.toString());
            }

            tabsContainer.querySelectorAll('.ql-tab').forEach(tab => {
                const tabType = new URL(tab.href).searchParams.get('type');
                tab.classList.toggle('is-active', tabType === type);
                tab.setAttribute('aria-selected', tabType === type);
            });

            updateTabHrefs();
        })
        .catch(error => {
            console.error('Error updating queue list:', error);
            contentArea.innerHTML = '<div class="empty-state empty-state--error"><p>Could not load the queue list. Please try refreshing the page.</p></div>';
        })
        .finally(() => {
            contentArea.classList.remove('is-loading');
            contentArea.removeAttribute('aria-busy');
            if (triggerBtn) {
                triggerBtn.classList.remove('is-loading');
                triggerBtn.disabled = false;
            }
        });
    }

    // Tabs
    tabsContainer.addEventListener('click', function (e) {
        const tab = e.target.closest('.ql-tab');
        if (tab) {
            e.preventDefault();
            const type = new URL(tab.href).searchParams.get('type');
            updateView(type, windowSelect.value);
        }
    });

    // Window/counter filter
    windowSelect.addEventListener('change', function () {
        const { type } = getCurrentFilters();
        updateView(type, this.value);
    });

    // Refresh button
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const { type } = getCurrentFilters();
            updateView(type, windowSelect.value, false, this);
        });
    }

    // Back/forward navigation
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.type && e.state.window) {
            windowSelect.value = e.state.window;
            updateView(e.state.type, e.state.window, false);
        } else {
            const params = new URLSearchParams(window.location.search);
            const type = params.get('type') || 'all';
            const windowVal = params.get('window') || 'all';
            windowSelect.value = windowVal;
            updateView(type, windowVal, false);
        }
    });

    // Keep tab hrefs reflecting the current window filter
    function updateTabHrefs() {
        const { window: windowFilter } = getCurrentFilters();
        tabsContainer.querySelectorAll('.ql-tab').forEach(tab => {
            const tabUrl = new URL(tab.href);
            tabUrl.searchParams.set('window', windowFilter);
            tab.href = tabUrl.toString();
        });
    }

    windowSelect.addEventListener('change', updateTabHrefs);
    updateTabHrefs(); // initial call

})();