/**
 * staff-list.js
 * Live/type-ahead search for staff-list.php.
 *
 * Same contract used across all list pages (document-list.php,
 * counter-list.php, staff-list.php): the PHP file responds to
 * fetch() requests carrying X-Requested-With: XMLHttpRequest with
 * JSON — { success, html, count, search } — instead of a full page,
 * so we can swap the results container in place without a reload.
 */
(function () {
    'use strict';

    const resultsEl    = document.getElementById('staff-results');
    const searchForm   = document.getElementById('staff-search-form');
    const searchInput  = document.getElementById('staff-search');
    const statusRegion = document.getElementById('staff-status-region');

    if (!resultsEl || !searchInput) return; // not on staff-list.php

    let debounceTimeout;
    function debounce(func, delay) {
        return function (...args) {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    function bindRowHandlers(scope) {
        // "Clear search" link inside the empty-row fragment — route it
        // through the same AJAX flow instead of a full reload.
        const clearLink = scope.querySelector('.js-clear-search');
        if (clearLink) {
            clearLink.addEventListener('click', function (e) {
                e.preventDefault();
                searchInput.value = '';
                loadStaffList(new URLSearchParams());
                searchInput.focus();
            });
        }
    }

    function loadStaffList(params, opts) {
        opts = opts || {};

        resultsEl.classList.add('is-loading');
        resultsEl.setAttribute('aria-busy', 'true');

        const url = 'staff-list.php' + (params.toString() ? '?' + params.toString() : '');

        return fetch(url, {
            method:  'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(data => {
            resultsEl.innerHTML = data.html;
            bindRowHandlers(resultsEl);

            if (opts.pushUrl !== false) {
                history.pushState({ search: data.search }, '', url);
            }
        })
        .catch(() => {
            if (statusRegion) statusRegion.textContent = 'Network error while loading staff.';
        })
        .finally(() => {
            resultsEl.classList.remove('is-loading');
            resultsEl.removeAttribute('aria-busy');
        });
    }

    const debouncedLoad = debounce(loadStaffList, 300);

    searchInput.addEventListener('input', function () {
        const params = new URLSearchParams();
        const term = searchInput.value.trim();
        if (term !== '') params.set('search', term);
        debouncedLoad(params);
    });

    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(debounceTimeout);
            const params = new URLSearchParams();
            const term = searchInput.value.trim();
            if (term !== '') params.set('search', term);
            loadStaffList(params);
        });
    }

    // Back/forward navigation: re-sync the list with the URL's search param.
    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        searchInput.value = params.get('search') || '';
        loadStaffList(params, { pushUrl: false });
    });

    bindRowHandlers(resultsEl); // in case of an empty state on initial load

})();