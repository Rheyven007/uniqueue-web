const BASE_URL = "/admin/queue/";

const currentDiv = document.getElementById("current-ticket");
const nextDiv = document.getElementById("next-ticket");
const waitingDiv = document.getElementById("waiting-list");
const lastDiv = document.getElementById("last-ticket");
const staffWindowInfo = document.getElementById("staffWindowInfo");
const windowNameText = document.getElementById("windowNameText");

// Only this many upcoming customers are listed individually in the
// "Waiting Queue" panel — the rest are represented by the
// "Last in Line" card so the dashboard never needs to scroll.
const MAX_VISIBLE_WAITING = 5;

// When a ticket's requested-documents list is long, the side column
// (queue type + documents) is given more of the card's width, and
// even more once it's this long — see getDocSpaceClass() below.
// All documents are always shown in full (never truncated); these
// thresholds only control how compact the tags get and how much
// extra room the card is given to fit them.
const MANY_DOCS_THRESHOLD = 4;
const LOTS_DOCS_THRESHOLD = 8;
const EXTREME_DOCS_THRESHOLD = 14;

const staffLeftGrid = document.querySelector(".staff-dashboard__left");

// Queue-type badge now lives in the section header (next to "Current
// Serving" / "Next in Queue") instead of stacked inside the card's side
// column, so it no longer competes with the Requested Documents list
// for vertical space.
const currentQueueTypeBadgeEl = document.getElementById("currentQueueTypeBadge");
const nextQueueTypeBadgeEl = document.getElementById("nextQueueTypeBadge");

const waitingCount = document.getElementById("waitingCount");
const servedCount = document.getElementById("servedCount");
const queueCount = document.getElementById("queueCount");

const btnCallNext = document.getElementById("callNextBtn");
const btnStart = document.getElementById("startServiceBtn");
const btnDone = document.getElementById("doneBtn");

let servedToday = 0;
let startMode = "start";
// Store previous queue state
let lastQueueData = "";
/* ==========================================================
   LOAD QUEUE
========================================================== */

async function loadQueue() {


    try {

        const response = await fetch(BASE_URL + "queue-data.php", {
            cache: "no-store",
            credentials: "same-origin"
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

    const data = await response.json();

    console.log("QUEUE DATA:", data);

    if (!data.success) {
        throw new Error(data.message || "Failed to load queue.");
    }

    // Check if anything changed
    const currentState = JSON.stringify(data);

    if (currentState === lastQueueData) {
        return; // Nothing changed, don't redraw
    }

    lastQueueData = currentState;

    renderStaffInfo(data.staff);
    renderCurrent(data.current);
    updateStartButton(data.current);
    renderNext(data.next);
    renderWaiting(data.waiting);
    updateGridBalance(data.current, data.next);

    } catch (err) {

        console.error("Queue Load Error:", err);

    }
}

/* ==========================================================
   STAFF & WINDOW INFO
========================================================== */

function renderStaffInfo(staff) {

    if (!windowNameText) {
        return;
    }

    if (!staff) {

        windowNameText.textContent =
            "Unable to load staff info.";

        return;

    }

    const windowLabel = staff.window_name
        ? staff.window_name
        : "No window assigned";

    windowNameText.textContent = windowLabel;

}

/* ==========================================================
   DOCUMENT-COUNT SPACE BALANCING
========================================================== */

// Appointment tickets never show the documents panel, so they
// never need extra side-column space either.
function getDocSpaceClass(documents, queueType) {

    if (String(queueType).trim().toLowerCase() === "appointment") {
        return "";
    }

    const count = Array.isArray(documents) ? documents.length : 0;

    if (count >= EXTREME_DOCS_THRESHOLD) {
        return " has-many-docs has-lots-docs has-extreme-docs";
    }

    if (count >= LOTS_DOCS_THRESHOLD) {
        return " has-many-docs has-lots-docs";
    }

    if (count >= MANY_DOCS_THRESHOLD) {
        return " has-many-docs";
    }

    return "";

}

/* ==========================================================
   GRID ROW BALANCING
   The left column (Current Serving / Next in Queue / Queue
   Controls) normally splits height evenly-ish between the two
   ticket cards. When one of them has a long document list, we
   shift that split so the heavy card gets more of the *same*
   fixed screen space — filling the screen more usefully instead
   of clipping or scrolling.
========================================================== */

function isDocHeavy(ticket) {

    if (!ticket) return false;

    if (String(ticket.queue_type).trim().toLowerCase() === "appointment") {
        return false;
    }

    const count = Array.isArray(ticket.documents) ? ticket.documents.length : 0;

    return count >= LOTS_DOCS_THRESHOLD;

}

function updateGridBalance(currentTicket, nextTicket) {

    if (!staffLeftGrid) return;

    const currentHeavy = isDocHeavy(currentTicket);
    const nextHeavy = isDocHeavy(nextTicket);

    staffLeftGrid.classList.remove(
        "grid--current-heavy",
        "grid--next-heavy",
        "grid--both-heavy"
    );

    if (currentHeavy && nextHeavy) {
        staffLeftGrid.classList.add("grid--both-heavy");
    } else if (currentHeavy) {
        staffLeftGrid.classList.add("grid--current-heavy");
    } else if (nextHeavy) {
        staffLeftGrid.classList.add("grid--next-heavy");
    }

}



function renderCurrent(ticket) {

    if (!ticket) {

        currentDiv.innerHTML = `
            <div class="current-ticket-card">
                <div class="empty">
                    No customer is currently being served.
                </div>
            </div>
        `;

        setHeaderQueueTypeBadge(currentQueueTypeBadgeEl, null);

        return;

    }

    setHeaderQueueTypeBadge(currentQueueTypeBadgeEl, ticket.queue_type);

    const sideContentCurrent =
        renderDocumentTags(ticket.documents, ticket.queue_type);

    const docSpaceClassCurrent = getDocSpaceClass(ticket.documents, ticket.queue_type);

    currentDiv.innerHTML = `

        <div class="current-ticket-card${docSpaceClassCurrent}">

            <div class="ticket-col ticket-col--main">

                <div class="ticket-label">
                    NOW SERVING
                </div>

                <h2>${ticket.queue_number}</h2>

                <h4>
                    ${ticket.first_name} ${ticket.last_name}
                </h4>

                <p>
                    SR Code: ${ticket.sr_code}
                </p>

                <small>
                    <span class="status-dot ${statusDotClass(ticket.status)}"></span>
                    Status: ${ticket.status}
                </small>

            </div>

            <div class="ticket-col ticket-col--side${sideContentCurrent.trim() ? "" : " is-empty"}">

                ${sideContentCurrent}

            </div>

        </div>

    `;

}

/* ==========================================================
   QUEUE TYPE BADGE (Appointment / Walk-in)
========================================================== */

function renderQueueTypePill(queueType) {

    if (!queueType) return "";

    const normalized = String(queueType).trim().toLowerCase();

    let label = null;
    let modifier = "";

    if (normalized === "appointment") {
        label = "Appointment";
        modifier = "appointment";
    } else if (normalized === "walk-in" || normalized === "walkin" || normalized === "walk in") {
        label = "Walk-in";
        modifier = "walkin";
    } else {
        return "";
    }

    return `
        <span class="queue-type-badge queue-type-badge--${modifier}">
            ${label}
        </span>
    `;

}

// Fills (or clears) the queue-type badge that sits in the section
// header, to the right of "Current Serving" / "Next in Queue".
function setHeaderQueueTypeBadge(el, queueType) {

    if (!el) return;

    const pill = renderQueueTypePill(queueType);

    el.innerHTML = pill;
    el.classList.toggle("is-empty", !pill.trim());

}

/* ==========================================================
   STATUS DOT — a small color indicator next to "Status: ..."
   (just a colored dot, not a full badge/pill treatment).
========================================================== */

function statusDotClass(status) {

    const normalized = String(status || "").trim().toLowerCase();

    if (normalized === "waiting") return "status-dot--waiting";
    if (normalized === "in_progress") return "status-dot--in-progress";
    if (normalized === "done" || normalized === "completed") return "status-dot--done";

    return "status-dot--default";

}

/* ==========================================================
   REQUESTED DOCUMENTS (TAG STYLE)
========================================================== */

function renderDocumentTags(documents, queueType) {

    const hasDocs = documents && documents.length;

    // A 2-column grid leaves an awkward empty cell for a single
    // document, and doesn't really help two either — one tag per
    // line reads cleaner when there are only 1 or 2 of them.
    const isCompact = hasDocs && documents.length <= 2;

    return `
        <div class="ticket-documents">
            <strong>Requested Documents</strong>
            <div class="document-tags${isCompact ? " document-tags--compact" : ""}">
                ${
                    hasDocs
                    ? documents.map(doc => `
                        <span class="doc-tag">
                            ${doc.name}
                            <span class="doc-tag__qty">${doc.quantity ?? 1}x</span>
                        </span>
                    `).join("")
                    : `<span class="doc-tag doc-tag--empty">None specified</span>`
                }
            </div>
        </div>
    `;
}

//UPDATE BUTTON TO PAUSE

function updateStartButton(ticket) {

    if (!btnStart) return;

    // Default
    startMode = "start";

    if (!ticket) {

        btnStart.innerHTML = `
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">

                <polygon points="5 3 19 12 5 21"/>

            </svg>

            Start Service
        `;

        btnStart.classList.remove("btn-warning");
        btnStart.classList.add("btn-success");

        return;
    }

    if (ticket.status === "in_progress") {

        startMode = "pause";

        btnStart.innerHTML = `
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">

                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>

            </svg>

            Pause Service
        `;

        btnStart.classList.remove("btn-success");
        btnStart.classList.add("btn-warning");

    } else {

        startMode = "start";

        btnStart.innerHTML = `
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">

                <polygon points="5 3 19 12 5 21"/>

            </svg>

            Start Service
        `;

        btnStart.classList.remove("btn-warning");
        btnStart.classList.add("btn-success");
    }

}

/* ==========================================================
   NEXT CUSTOMER
========================================================== */

function renderNext(ticket) {

    if (!nextDiv) return;

    if (!ticket) {

        nextDiv.innerHTML = `
            <div class="current-ticket-card">
                <div class="empty">
                    No one is next in queue.
                </div>
            </div>
        `;

        setHeaderQueueTypeBadge(nextQueueTypeBadgeEl, null);

        return;
    }

    setHeaderQueueTypeBadge(nextQueueTypeBadgeEl, ticket.queue_type);

    const sideContentNext =
        renderDocumentTags(ticket.documents, ticket.queue_type);

    const docSpaceClassNext = getDocSpaceClass(ticket.documents, ticket.queue_type);

    nextDiv.innerHTML = `
        <div class="current-ticket-card${docSpaceClassNext}">

            <div class="ticket-col ticket-col--main">

                <div class="ticket-label ticket-label--next">
                    UP NEXT
                </div>

                <h2>${ticket.queue_number}</h2>

                <h4>${ticket.first_name} ${ticket.last_name}</h4>

                <p>SR Code: ${ticket.sr_code}</p>

                <small><span class="status-dot ${statusDotClass(ticket.status)}"></span>Status: ${ticket.status}</small>

            </div>

            <div class="ticket-col ticket-col--side${sideContentNext.trim() ? "" : " is-empty"}">

                ${sideContentNext}

            </div>

        </div>
    `;
}



/* ==========================================================
   WAITING QUEUE
========================================================== */

function renderWaiting(waiting = []) {

    // Update queue counter (reflects the FULL queue, not just the
    // trimmed list rendered below).
    if (queueCount) {
        queueCount.textContent = `In Queue: ${waiting.length}`;
    }

    // The "Last in Line" card is rendered separately from whatever
    // is visible here, so it always reflects the true last person —
    // even once the list below has been trimmed down.
    renderLast(waiting);

    if (waiting.length === 0) {

        waitingDiv.innerHTML = `
            <div class="empty">
                No customers waiting.
            </div>
        `;

        return;

    }

    // Only show the first N in the list itself; anyone beyond that
    // is summarized by the queue count + "Last in Line" card instead
    // of forcing the panel to scroll.
    const visibleWaiting = waiting.slice(0, MAX_VISIBLE_WAITING);
    const hiddenCount = waiting.length - visibleWaiting.length;

    let html = "";

    visibleWaiting.forEach((ticket, index) => {

       html += `

            <div class="queue-item">

                <div class="queue-details">

                    <div class="queue-title">
                        ${ticket.queue_number}
                    </div>

                    <div class="queue-subtitle" title="${ticket.first_name} ${ticket.last_name}">
                        ${ticket.first_name} ${ticket.last_name}
                    </div>

                </div>

                <div class="queue-side">

                    <span class="queue-status waiting">
                        Waiting
                    </span>

                    <span class="queue-time">
                        In Queue
                    </span>

                </div>

            </div>

            `;

    });

    if (hiddenCount > 0) {

        html += `
            <div class="queue-more">
                +${hiddenCount} more waiting &middot; see Last in Line
            </div>
        `;

    }

    waitingDiv.innerHTML = html;

}

/* ==========================================================
   LAST IN LINE
========================================================== */

function renderLast(waiting = []) {

    if (!lastDiv) return;

    // Nothing to show if the queue is empty, or if the full queue
    // already fits inside the visible waiting list (no one is
    // "hidden" beyond it).
    if (waiting.length === 0) {

        lastDiv.innerHTML = `
            <div class="empty">
                No one else waiting.
            </div>
        `;

        return;
    }

    if (waiting.length <= MAX_VISIBLE_WAITING) {

        lastDiv.innerHTML = `
            <div class="empty">
                That's everyone in line.
            </div>
        `;

        return;
    }

    const ticket = waiting[waiting.length - 1];
    const position = waiting.length;

    lastDiv.innerHTML = `
        <div class="last-ticket-card">

            <div class="last-ticket-card__position">
                #${position}
            </div>

            <div class="last-ticket-card__details">
                <div class="queue-title">${ticket.queue_number}</div>
                <div class="queue-subtitle" title="${ticket.first_name} ${ticket.last_name}">
                    ${ticket.first_name} ${ticket.last_name}
                </div>
            </div>

            <span class="queue-status waiting">
                Waiting
            </span>

        </div>
    `;

}

/* ==========================================================
   BUTTON LOADING
========================================================== */

function setButtons(disabled) {

    [btnCallNext, btnStart, btnDone].forEach(btn => {

        if (btn) {

            btn.disabled = disabled;

        }

    });

}

/* ==========================================================
   ACTIONS
========================================================== */

async function queueAction(file) {

    setButtons(true);

    try {

        const response = await fetch(BASE_URL + file, {
            method: "POST",
            credentials: "same-origin"
        });

        if (!response.ok) {
            throw new Error("HTTP " + response.status);
        }

        const data = await response.json();

        if (!data.success) {
            console.warn(data.message);
            return;
        }

        if (file === "queue-done.php") {

            servedToday++;

            if (servedCount) {
                servedCount.textContent = servedToday;
            }

        }

        // Refresh dashboard immediately
        await loadQueue();

    } catch (err) {

        console.error("Queue Action Error:", err);

    } finally {

        setButtons(false);

    }

}

/* ==========================================================
   EVENTS
========================================================== */

btnCallNext?.addEventListener("click", () => {

    queueAction("call-next.php");

});

btnStart?.addEventListener("click", () => {

    if (startMode === "start") {

        queueAction("start-service.php");

    } else {

        queueAction("pause-service.php");

    }

});

btnDone?.addEventListener("click", () => {

    queueAction("queue-done.php");

});

/* ==========================================================
   INITIALIZE
========================================================== */

loadQueue();

setInterval(loadQueue, 3000);