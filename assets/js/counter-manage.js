/**
 * counter-manage.js
 * Handles the Open/Close toggle for service windows.
 * Works on both counter-list.php and office-dashboard.php —
 * any page that renders buttons with class "btn-toggle-counter".
 */

(function () {
  'use strict';

  /**
   * Toggle a single window's open/closed status.
   * @param {HTMLButtonElement} btn  The button that was clicked.
   */
  async function toggleCounter(btn) {
    const id     = btn.dataset.id;
    const status = btn.dataset.status; // current status: 'open' | 'closed'

    if (!id || !status) return;

    // Disable button while request is in flight
    btn.disabled = true;
    const originalText = btn.textContent.trim();
    btn.textContent = 'Updating…';

    try {
      const formData = new FormData();
      formData.append('id',     id);
      formData.append('status', status);

      const response = await fetch('/admin/counter/counter-toggle.php', {
        method:      'POST',
        credentials: 'same-origin',
        body:        formData,
      });

      if (!response.ok) {
        throw new Error(`Server returned HTTP ${response.status}`);
      }

      let data;
      try {
        data = await response.json();
      } catch {
        throw new Error('Invalid JSON response from server. Check counter-toggle.php output.');
      }

      if (data.success) {
        const newStatus = data.new_status; // 'open' | 'closed'

        // ── Update button ──────────────────────────────────────────────────
        btn.dataset.status  = newStatus;
        btn.textContent     = newStatus === 'open' ? 'Close Window' : 'Open Window';

        // ── Update status badge / indicator (counter-list.php) ─────────────
        const row = btn.closest('tr');
        if (row) {
          const indicator = row.querySelector('.status-indicator');
          if (indicator) {
            indicator.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            indicator.className   = `status-indicator ${newStatus}`;
          }
        }

        // ── Update window card (office-dashboard.php) ──────────────────────
        const card = btn.closest('.window-card');
        if (card) {
          // Update card class
          card.classList.remove('is-open', 'is-closed');
          card.classList.add(`is-${newStatus}`);

          // Update status dot
          const dot = card.querySelector('.status-dot');
          if (dot) {
            dot.classList.remove('open', 'closed');
            dot.classList.add(newStatus);
            dot.title      = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            dot.ariaLabel  = `Status: ${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}`;
          }

          // Update inline status text
          const metaStrong = card.querySelector('.window-card__meta strong');
          if (metaStrong) {
            metaStrong.textContent  = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            metaStrong.style.color  = newStatus === 'open' ? 'var(--green)' : 'var(--muted)';
          }

          // Update empty-slot text if window is now idle
          const emptySlot = card.querySelector('.empty-slot');
          if (emptySlot) {
            emptySlot.textContent = newStatus === 'open' ? 'Idle — ready for next' : 'Window closed';
          }

          // Update aria-label on the button itself
          const cardName = card.querySelector('.window-card__name');
          const name     = cardName ? cardName.textContent.trim() : 'window';
          btn.setAttribute('aria-label', `${newStatus === 'open' ? 'Close' : 'Open'} ${name}`);
        }

      } else {
        // Server responded success:false
        alert(`Could not update window status: ${data.message || 'Unknown error.'}`);
        btn.textContent = originalText;
      }

    } catch (err) {
      console.error('[counter-manage]', err);
      alert(`Network error: ${err.message}\n\nCheck your connection or server logs.`);
      btn.textContent = originalText;
    } finally {
      btn.disabled = false;
    }
  }

  // ── Event delegation — works for dynamically rendered cards too ────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-toggle-counter');
    if (btn) {
      e.preventDefault();
      toggleCounter(btn);
    }
  });

})();

/**
 * Live/type-ahead search for counter-list.php.
 *
 * Same contract used across all list pages (document-list.php,
 * staff-list.php, counter-list.php): the PHP file responds to fetch()
 * requests carrying X-Requested-With: XMLHttpRequest with JSON —
 * { success, html, count, search } — instead of a full page, so we can
 * swap the results container in place without a reload.
 */
(function () {
  'use strict';

  const resultsEl    = document.getElementById('counter-results');
  const searchForm   = document.getElementById('counter-search-form');
  const searchInput  = document.getElementById('counter-search');
  const statusRegion = document.getElementById('counter-status-region');

  if (!resultsEl || !searchInput) return; // not on counter-list.php

  let debounceTimeout;
  function debounce(func, delay) {
    return function (...args) {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => func.apply(this, args), delay);
    };
  }

  function announce(term, count, success) {
    if (!statusRegion) return;
    statusRegion.textContent = !success
      ? 'Unable to load counters.'
      : term
        ? `${count} counter${count === 1 ? '' : 's'} found for "${term}".`
        : `Showing all counters.`;
  }

  function bindResultHandlers(scope) {
    // "Clear search" link inside the empty-state fragment — route it
    // through the same AJAX flow instead of a full reload.
    const clearLink = scope.querySelector('.js-clear-search');
    if (clearLink) {
      clearLink.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value = '';
        loadCounterList(new URLSearchParams());
        searchInput.focus();
      });
    }
  }

  function loadCounterList(params, opts) {
    opts = opts || {};

    resultsEl.classList.add('is-loading');
    resultsEl.setAttribute('aria-busy', 'true');

    const url = 'counter-list.php' + (params.toString() ? '?' + params.toString() : '');

    return fetch(url, {
      method:  'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(r => r.json())
    .then(data => {
      resultsEl.innerHTML = data.html;
      bindResultHandlers(resultsEl);

      if (opts.pushUrl !== false) {
        history.pushState({ search: data.search }, '', url);
      }

      announce(data.search, data.count, data.success);
    })
    .catch(() => {
      if (statusRegion) statusRegion.textContent = 'Network error while loading counters.';
    })
    .finally(() => {
      resultsEl.classList.remove('is-loading');
      resultsEl.removeAttribute('aria-busy');
    });
  }

  const debouncedLoad = debounce(loadCounterList, 300);

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
      loadCounterList(params);
    });
  }

  // Back/forward navigation: re-sync the list with the URL's search param.
  window.addEventListener('popstate', function () {
    const params = new URLSearchParams(window.location.search);
    searchInput.value = params.get('search') || '';
    loadCounterList(params, { pushUrl: false });
  });

  bindResultHandlers(resultsEl); // in case of an empty state on initial load

})();