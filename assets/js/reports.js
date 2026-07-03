// assets/js/reports.js — Report interactions

(function () {
    'use strict';

    const reportForm = document.querySelector('.report-filters form');
    if (!reportForm) return;

    // Auto-submit when date changes
    const dateInput = reportForm.querySelector('input[type="date"]');
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            reportForm.submit();
        });
    }

    console.log('Reports module controls initialized.');
})();