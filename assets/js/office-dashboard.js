// assets/js/office-dashboard.js

(function () {
    "use strict";

    if (typeof CURRENT_OFFICE_ID === "undefined") return;

    // Theme palette (kept in sync with office-dashboard.css)
    const COLORS = {
        red:    "#C21010",
        green:  "#1a8c4e",
        teal:   "#0d9488",
        amber:  "#d97706",
        blue:   "#2563eb",
        violet: "#7c3aed",
        muted:  "#8a7070",
        grid:   "#ede8e8"
    };

    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = "#4a3333";
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = "#1a0a0a";

    const charts = {
        queueStatus: null,
        queueType: null,
        hourly: null,
        window: null,
        documents: null
    };

    // ── Initial paint from data already embedded by PHP (instant, no round-trip) ──
    function renderInitial() {
        drawQueueStatus(queueStatus);
        drawQueueTypes(queueTypes);
        drawHourly(hourlyLabels, hourlyData);
        drawWindows(windowLabels, windowData);
        drawDocuments(documentLabels, documentData);
    }

    // ── Live refresh from the API, silently keeps last render if unreachable ──
    function refreshDashboard() {
        fetch(`/api/dashboard-stats.php?office_id=${CURRENT_OFFICE_ID}`)
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;

                drawQueueStatus(data.queueStatus);
                drawQueueTypes(data.queueTypes);
                drawHourly(data.hourlyLabels, data.hourlyData);
                drawWindows(data.windowLabels, data.windowData);
                drawDocuments(data.documentLabels, data.documentData);
            })
            .catch(() => {
                // Live-refresh endpoint unavailable — keep showing the last
                // known good render instead of leaving blank charts.
            });
    }

    function hasData(values) {
        return Array.isArray(values) && values.some(v => Number(v) > 0);
    }

    function showEmpty(canvas, show) {
        const card = canvas.closest(".chart-card");
        if (!card) return;
        let msg = card.querySelector(".chart-empty");
        if (show) {
            canvas.style.display = "none";
            if (!msg) {
                msg = document.createElement("div");
                msg.className = "chart-empty";
                msg.textContent = "No data yet today";
                card.appendChild(msg);
            }
        } else {
            canvas.style.display = "";
            if (msg) msg.remove();
        }
    }

    function drawQueueStatus(values) {
        const canvas = document.getElementById("queueStatusChart");
        showEmpty(canvas, !hasData(values));
        if (!hasData(values)) return;

        if (charts.queueStatus) charts.queueStatus.destroy();
        charts.queueStatus = new Chart(canvas, {
            type: "doughnut",
            data: {
                labels: ["Completed", "Cancelled"],
                datasets: [{
                    data: values,
                    backgroundColor: [COLORS.amber, COLORS.teal, COLORS.green, COLORS.muted],
                    borderColor: "#fff",
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: { position: "bottom", labels: { boxWidth: 10, padding: 14 } }
                }
            }
        });
    }

    function drawQueueTypes(values) {
        const canvas = document.getElementById("queueTypeChart");
        showEmpty(canvas, !hasData(values));
        if (!hasData(values)) return;

        if (charts.queueType) charts.queueType.destroy();
        charts.queueType = new Chart(canvas, {
            type: "pie",
            data: {
                labels: ["Walk-in", "Appointment"],
                datasets: [{
                    data: values,
                    backgroundColor: [COLORS.red, COLORS.blue],
                    borderColor: "#fff",
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "bottom", labels: { boxWidth: 10, padding: 14 } }
                }
            }
        });
    }

    function drawHourly(labels, values) {
        const canvas = document.getElementById("hourlyChart");
        showEmpty(canvas, !hasData(values));
        if (!hasData(values)) return;

        if (charts.hourly) charts.hourly.destroy();
        charts.hourly = new Chart(canvas, {
            type: "line",
            data: {
                labels: labels,
                datasets: [{
                    label: "Transactions",
                    data: values,
                    borderColor: COLORS.red,
                    backgroundColor: "rgba(194,16,16,0.08)",
                    pointBackgroundColor: COLORS.red,
                    pointRadius: 3,
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { precision: 0 } }
                }
            }
        });
    }

    function drawWindows(labels, values) {
        const canvas = document.getElementById("windowChart");
        showEmpty(canvas, !hasData(values));
        if (!hasData(values)) return;

        if (charts.window) charts.window.destroy();
        charts.window = new Chart(canvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                    label: "Completed",
                    data: values,
                    backgroundColor: COLORS.teal,
                    borderRadius: 6,
                    maxBarThickness: 46
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { precision: 0 } }
                }
            }
        });
    }

    function drawDocuments(labels, values) {
        const canvas = document.getElementById("documentsChart");
        showEmpty(canvas, !hasData(values));
        if (!hasData(values)) return;

        if (charts.documents) charts.documents.destroy();
        charts.documents = new Chart(canvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                    label: "Requests",
                    data: values,
                    backgroundColor: COLORS.violet,
                    borderRadius: 6,
                    maxBarThickness: 22
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: "y",
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    document.addEventListener("DOMContentLoaded", () => {

        // Initial chart render from PHP data
        renderInitial();

        // Auto fetch latest dashboard data
        refreshDashboard();

        // Update every 15 seconds
        setInterval(refreshDashboard, REFRESH_MS);

    });

})();