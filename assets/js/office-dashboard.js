// assets/js/office-dashboard.js

(function () {
    "use strict";

    if (typeof CURRENT_OFFICE_ID === "undefined") return;

    // ── Theme palette ────────────────────────────────────────────────────
    // One cohesive family built off the brand maroon, instead of clashing
    // primary colors. Each chart gets exactly two tones max.
    const COLORS = {
        maroon:      '#660810', // --brand-primary
        maroonLight: '#8a2e35', // --brand-primary-light
        rust:        '#c1793f', // A warm accent
        teal:        '#0d9488', // A cool accent
        slate:       '#5b6b8c', // A neutral accent
        ink:         '#1f1f1f', // --text
        inkMuted:    '#7a7a7a', // --gray
        grid:        'rgba(102, 8, 16, 0.08)', // --border
        track:       'rgba(102, 8, 16, 0.06)'  // --primary-soft
    };

    // Vivid multi-hue set for donuts/pies — this is the palette that gives
    // charts that colorful "showcase" look instead of a flat single-tone fill.
    const VIVID = ['#8a2e35', '#c1793f', '#2e7d32', '#c62828', '#5b6b8c', '#f39c12', '#0d9488', '#7b1fa2'];

    const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    const charts = {
        queueOverview: null,
        hourly: null,
        window: null,
        documents: null,
        feedback: null,
        college: null
    };

    // Both datasets are kept in memory so the Status/Type toggle can swap
    // the single donut instantly without a re-fetch.
    const overviewData = {
        all:    { labels: ["Waiting", "Serving", "Completed", "Cancelled"], colors: [VIVID[0], VIVID[1], VIVID[2], VIVID[3]], values: [], subtitle: "Ticket Distribution" },
        status: { labels: ["Completed", "Cancelled"], colors: [VIVID[2], VIVID[3]], values: [], subtitle: "Completed vs Cancelled" },
        type:   { labels: ["Walk-in", "Appointment"], colors: [VIVID[0], VIVID[4]], values: [], subtitle: "Walk-in vs Appointment" }
    };
    let overviewView = "all";

    // ── Initial paint from data already embedded by PHP (instant, no round-trip) ──
    function renderInitial() {
        setOverviewData(queueAll, queueStatus, queueTypes);
        drawQueueOverview();
        drawHourly(hourlyLabels, hourlyData);
        drawWindows(windowLabels, windowData);
        drawDocuments(documentLabels, documentData);
        drawFeedback(feedbackRatings);
        drawColleges(collegeLabels, collegeData);
    }

    // ── Live refresh from the API, silently keeps last render if unreachable ──
    function refreshDashboard() {
        const params = new URLSearchParams({
            office_id: CURRENT_OFFICE_ID,
            range: CURRENT_RANGE,
            from: CURRENT_FROM,
            to: CURRENT_TO
        });

        fetch(`/api/dashboard-stats.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;

                setOverviewData(data.queueAll, data.queueStatus, data.queueTypes);
                drawQueueOverview();
                drawHourly(data.hourlyLabels, data.hourlyData);
                drawWindows(data.windowLabels, data.windowData);
                drawDocuments(data.documentLabels, data.documentData);
                drawFeedback(data.feedbackRatings);
                drawColleges(data.collegeLabels, data.collegeData);
            })
            .catch(() => {
                // Live-refresh endpoint unavailable — keep showing the last
                // known good render instead of leaving blank charts.
            });
    }

    function hasData(values) {
        return Array.isArray(values) && values.some(v => Number(v) > 0);
    }

    function showEmpty(container, show) {
        if (!container) return;
        const wrap = container.closest(".chart-card");
        if (!wrap) return;
        let msg = wrap.querySelector(".chart-empty");
        if (show) {
            container.style.display = "none";
            if (!msg) {
                msg = document.createElement("div");
                msg.className = "chart-empty";
                wrap.appendChild(msg);
            }
            const isToday = (typeof IS_TODAY === "undefined") || IS_TODAY;
            msg.textContent = isToday ? "No data yet today" : "No data for this date range";
        } else {
            container.style.display = "";
            if (msg) msg.remove();
        }
    }

    function donutLegend() {
        return {
            position: "bottom",
            fontFamily: FONT,
            fontSize: "11.5px",
            fontWeight: 500,
            labels: { colors: COLORS.inkMuted },
            markers: { size: 5, offsetX: -2 },
            itemMargin: { horizontal: 10, vertical: 4 }
        };
    }

    function donutTotalLabel() {
        return {
            show: true,
            label: "Total",
            fontSize: "11px",
            fontWeight: 600,
            color: COLORS.inkMuted
        };
    }

    function setOverviewData(allValues, statusValues, typeValues) {
        overviewData.all.values = (allValues || []).map(Number);
        overviewData.status.values = (statusValues || []).map(Number);
        overviewData.type.values = (typeValues || []).map(Number);
    }

    function drawQueueOverview() {
        const el = document.querySelector("#queueOverviewChart");
        const view = overviewData[overviewView];
        const values = view.values;

        showEmpty(el, !hasData(values));

        const subtitleEl = document.getElementById("queueOverviewSubtitle");
        if (subtitleEl) {
            const isToday = (typeof IS_TODAY === "undefined") || IS_TODAY;
            subtitleEl.textContent = (isToday ? "Today's " : "") + view.subtitle;
        }

        if (!hasData(values)) return;

        const total = values.reduce((a, b) => a + Number(b), 0);

        if (charts.queueOverview) { charts.queueOverview.dispose(); charts.queueOverview = null; }
        const chart = echarts.init(el);
        charts.queueOverview = chart;

        const option = {
            tooltip: {
                trigger: "item",
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0,
                formatter: (p) => `${p.marker} ${p.name}: <b>${p.value}</b> ticket${p.value === 1 ? "" : "s"} (${p.percent}%)`
            },
            legend: {
                top: 0,
                icon: "circle",
                itemWidth: 9,
                itemHeight: 9,
                textStyle: { color: COLORS.ink, fontFamily: FONT, fontSize: 12, fontWeight: 500 },
                itemGap: 16
            },
            color: view.colors,
            series: [{
                name: "Queue Overview",
                type: "pie",
                radius: ["50%", "78%"],
                center: ["50%", "56%"],
                avoidLabelOverlap: false,
                roseType: false,
                itemStyle: {
                    borderRadius: 10,
                    borderWidth: 3,
                    borderColor: "#fff",
                    shadowBlur: 12,
                    shadowColor: "rgba(0,0,0,0.10)"
                },
                label: {
                    show: true,
                    position: "center",
                    formatter: `{a|TOTAL}\n{b|${total}}`,
                    rich: {
                        a: { fontSize: 11, fontWeight: 700, color: COLORS.inkMuted, lineHeight: 18, letterSpacing: 1 },
                        b: { fontSize: 26, fontWeight: 800, color: COLORS.ink, lineHeight: 30 }
                    }
                },
                labelLine: { show: false },
                emphasis: {
                    scale: true,
                    scaleSize: 8,
                    itemStyle: { shadowBlur: 20, shadowColor: "rgba(0,0,0,0.25)" }
                },
                data: view.labels.map((label, i) => ({ value: Number(values[i]) || 0, name: label }))
            }]
        };
        chart.setOption(option, true);
    }

    function drawHourly(labels, values) {
        const el = document.querySelector("#hourlyChart");
        showEmpty(el, !hasData(values));
        if (!hasData(values)) return;

        if (charts.hourly) { charts.hourly.dispose(); }
        const chart = echarts.init(el);
        charts.hourly = chart;

        const option = {
            tooltip: {
                trigger: 'axis',
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0,
                axisPointer: { lineStyle: { color: COLORS.grid } }
            },
            grid: { left: '3%', right: '4%', bottom: '3%', top: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: labels,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: COLORS.inkMuted, fontFamily: FONT }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: COLORS.grid, type: 'dashed' } },
                axisLabel: { color: COLORS.inkMuted, fontFamily: FONT }
            },
            series: [{
                name: "Transactions",
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                showSymbol: false,
                data: values.map(Number),
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(91, 143, 249, 0.45)' },
                        { offset: 0.6, color: 'rgba(138, 46, 53, 0.18)' }, // --brand-primary-light
                        { offset: 1, color: 'rgba(138, 46, 53, 0)' }
                    ])
                },
                itemStyle: { color: VIVID[0], borderColor: '#fff', borderWidth: 2 },
                lineStyle: {
                    width: 3,
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: VIVID[0] },
                        { offset: 1, color: COLORS.maroonLight }
                    ])
                },
                emphasis: { focus: 'series' }
            }]
        };
        chart.setOption(option, true);
    }

    function drawWindows(labels, values) {
        const el = document.querySelector("#windowChart");
        showEmpty(el, !hasData(values));
        if (!hasData(values)) return;

        if (charts.window) { charts.window.dispose(); }
        const chart = echarts.init(el);
        charts.window = chart;

        const option = {
            tooltip: {
                trigger: 'axis',
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0,
                formatter: '{b}<br/>{a}: <b>{c}</b>'
            },
            grid: { left: '3%', right: '4%', bottom: '3%', top: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: labels,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: COLORS.inkMuted, fontFamily: FONT }
            },
            yAxis: { type: 'value', show: false },
            series: [{
                name: 'Completed',
                type: 'bar',
                data: values.map(Number),
                barWidth: '45%',
                itemStyle: {
                    borderRadius: [8, 8, 0, 0],
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: '#0d9488' }, // teal
                        { offset: 1, color: COLORS.teal }
                    ])
                },
                emphasis: {
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: '#2e7d32' }, // success
                            { offset: 1, color: VIVID[6] }
                        ])
                    }
                }
            }]
        };
        chart.setOption(option, true);
    }

    function drawDocuments(labels, values) {
        const el = document.querySelector("#documentsChart");
        showEmpty(el, !hasData(values));
        if (!hasData(values)) return;

        if (charts.documents) { charts.documents.dispose(); }
        const chart = echarts.init(el);
        charts.documents = chart;

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0
            },
            grid: { left: '3%', right: '4%', bottom: '3%', top: '10%', containLabel: true },
            xAxis: { type: 'value', show: false },
            yAxis: {
                type: 'category',
                data: labels,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: COLORS.inkMuted, fontFamily: FONT, fontSize: 11 }
            },
            series: [{
                name: 'Requests',
                type: 'bar',
                data: values.map(Number),
                barWidth: '55%',
                itemStyle: {
                    borderRadius: [0, 8, 8, 0],
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: COLORS.maroonLight },
                        { offset: 1, color: COLORS.slate }
                    ])
                }
            }]
        };
        chart.setOption(option, true);
    }

    function drawFeedback(values) {
        const el = document.querySelector("#feedbackChart");
        showEmpty(el, !hasData(values));
        if (!hasData(values)) return;

        if (charts.feedback) { charts.feedback.dispose(); }
        const chart = echarts.init(el);
        charts.feedback = chart;

        const option = {
            tooltip: {
                trigger: 'axis',
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0
            },
            grid: { left: '3%', right: '4%', bottom: '3%', top: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: ["1 Star", "2 Stars", "3 Stars", "4 Stars", "5 Stars"],
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { color: COLORS.inkMuted, fontFamily: FONT, fontSize: 11 }
            },
            yAxis: { type: 'value', show: false },
            series: [{
                name: 'Ratings',
                type: 'bar',
                data: values.map(Number),
                barWidth: '55%',
                itemStyle: {
                    borderRadius: [8, 8, 0, 0],
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: COLORS.rust },
                        { offset: 1, color: '#f39c12' } // warning
                    ])
                }
            }]
        };
        chart.setOption(option, true);
    }

    function drawColleges(labels, values) {
        const el = document.querySelector("#collegeChart");
        showEmpty(el, !hasData(values));
        if (!hasData(values)) return;

        if (charts.college) { charts.college.dispose(); }
        const chart = echarts.init(el);
        charts.college = chart;

        const option = {
            tooltip: {
                trigger: 'item',
                textStyle: { fontFamily: FONT },
                backgroundColor: "rgba(30, 20, 20, 0.92)",
                borderWidth: 0,
                formatter: (p) => `${p.marker} ${p.name}: <b>${p.value}</b> (${p.percent}%)`
            },
            legend: {
                orient: 'vertical',
                left: 'left',
                top: 'center',
                icon: 'circle',
                itemWidth: 9,
                itemHeight: 9,
                itemGap: 12,
                textStyle: { color: COLORS.ink, fontFamily: FONT, fontSize: 12, fontWeight: 500 }
            },
            color: VIVID,
            series: [{
                name: 'Tickets by College',
                type: 'pie',
                radius: ['48%', '75%'],
                center: ['68%', '50%'],
                avoidLabelOverlap: false,
                itemStyle: {
                    borderRadius: 8,
                    borderWidth: 3,
                    borderColor: '#fff',
                    shadowBlur: 12,
                    shadowColor: 'rgba(0,0,0,0.10)'
                },
                label: { show: false, position: 'center' },
                emphasis: {
                    scale: true,
                    scaleSize: 8,
                    label: {
                        show: true,
                        fontSize: 18,
                        fontWeight: 'bold',
                        fontFamily: FONT,
                        color: COLORS.ink,
                        formatter: '{b}\n{c}'
                    },
                    itemStyle: { shadowBlur: 20, shadowColor: 'rgba(0,0,0,0.25)' }
                },
                labelLine: { show: false },
                data: labels.map((label, i) => ({ value: values[i], name: label }))
            }]
        };
        chart.setOption(option, true);
    }

    // ── Date-range slicer wiring ──────────────────────────────────────────
    function initSlicer() {
        const form   = document.getElementById("dashboard-slicer");
        const select = document.getElementById("slicer-range");
        const custom = document.getElementById("slicer-custom");
        const fromEl = document.getElementById("slicer-from");
        const toEl   = document.getElementById("slicer-to");
        const applyBtn = form ? form.querySelector(".dashboard-slicer__apply") : null;
        if (!form || !select) return;

        function goTo(range, from, to) {
            const url = new URL(window.location.href);
            url.searchParams.set("range", range);
            if (range === "custom") {
                url.searchParams.set("from", from || "");
                url.searchParams.set("to", to || "");
            } else {
                url.searchParams.delete("from");
                url.searchParams.delete("to");
            }
            window.location.href = url.toString();
        }

        select.addEventListener("change", (e) => {
            if (select.value === "custom") {
                custom.classList.add("is-visible");
                // Wait for the user to pick both dates and hit Apply
                return;
            }
            custom.classList.remove("is-visible");
            goTo(select.value, null, null);
        });

        if (applyBtn) {
            applyBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                goTo("custom", fromEl ? fromEl.value : "", toEl ? toEl.value : "");
            });
        }

        // Belt-and-suspenders: if anything still triggers a native submit
        // (e.g. pressing Enter inside a date field), handle it the same way
        // instead of letting it fall through to whatever else is listening.
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            e.stopPropagation();
            goTo(select.value, fromEl ? fromEl.value : "", toEl ? toEl.value : "");
        });
    }

    // ── Queue Overview All/Status/Type toggle ──────────────────────────────
    function initOverviewToggle() {
        const toggle = document.getElementById("queueOverviewToggle");
        if (!toggle) return;

        toggle.addEventListener("click", (e) => {
            const btn = e.target.closest(".chart-toggle__btn");
            if (!btn || !toggle.contains(btn)) return;

            const view = btn.dataset.view;
            if (!view || view === overviewView) return;

            toggle.querySelectorAll(".chart-toggle__btn").forEach((b) => {
                b.classList.toggle("is-active", b === btn);
                b.setAttribute("aria-selected", b === btn ? "true" : "false");
            });

            overviewView = view;
            drawQueueOverview();
        });
    }

    document.addEventListener("DOMContentLoaded", () => {

        // Initial chart render from PHP data — already filtered by the
        // selected date range (today or historical).
        renderInitial();
        initSlicer();
        initOverviewToggle();

        // Handle window resizing for ECharts
        window.addEventListener('resize', function() {
            Object.values(charts).forEach(chart => {
                if (chart) chart.resize();
            });
        });
        // Live AJAX refresh only applies to "Today". Historical ranges
        // are static and must not be re-fetched/overwritten — the
        // /api/dashboard-stats.php endpoint currently has no concept of
        // range/from/to, so calling it here would silently snap every
        // chart back to today's numbers regardless of what's selected.
        if (typeof IS_TODAY === "undefined" || IS_TODAY) {
            refreshDashboard();
            setInterval(refreshDashboard, REFRESH_MS);
        }

    });

})();