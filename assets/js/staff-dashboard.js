const BASE_URL = "/admin/queue/";

const currentDiv = document.getElementById("current-ticket");
const nextDiv = document.getElementById("next-ticket");
const waitingDiv = document.getElementById("waiting-list");
const staffWindowInfo = document.getElementById("staffWindowInfo");

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

    } catch (err) {

        console.error("Queue Load Error:", err);

    }
}

/* ==========================================================
   STAFF & WINDOW INFO
========================================================== */

function renderStaffInfo(staff) {

    if (!staffWindowInfo) {
        return;
    }

    if (!staff) {

        staffWindowInfo.textContent =
            "Unable to load staff info.";

        return;

    }

    const windowLabel = staff.window_name
        ? staff.window_name
        : "No window assigned";

    staffWindowInfo.textContent = windowLabel;

}

/* ==========================================================
   CURRENT CUSTOMER
========================================================== */

function renderCurrent(ticket) {

    if (!ticket) {

        currentDiv.innerHTML = `
            <div class="empty">
                No customer is currently being served.
            </div>
        `;

        return;

    }

    currentDiv.innerHTML = `

        <div class="current-ticket-card">

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

            ${renderDocumentTags(ticket.documents, ticket.queue_type)}

            <small>
                Status: ${ticket.status}
            </small>

        </div>

        ${renderQueueTypeBadge(ticket.queue_type)}

    `;

}

/* ==========================================================
   QUEUE TYPE BADGE (Appointment / Walk-in)
========================================================== */

function renderQueueTypeBadge(queueType) {

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
        <div class="queue-type-badge-wrap">
            <span class="queue-type-badge queue-type-badge--${modifier}">
                ${label}
            </span>
        </div>
    `;

}

/* ==========================================================
   REQUESTED DOCUMENTS (TAG STYLE)
========================================================== */

function renderDocumentTags(documents, queueType) {

    // Appointment → don't show the Requested Documents section
    if (String(queueType).toLowerCase() === "appointment") {
        return "";
    }

    const hasDocs = documents && documents.length;

    return `
        <div class="ticket-documents">
            <strong>Requested Documents</strong>
            <div class="document-tags">
                ${
                    hasDocs
                    ? documents.map(doc => `
                        <span class="doc-tag">
                            ${doc.name}
                            ${doc.quantity > 1 ? `<span class="doc-tag__qty">${doc.quantity}x</span>` : ""}
                        </span>
                    `).join("")
                    : ""
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

        return;
    }

    nextDiv.innerHTML = `
        <div class="current-ticket-card">

            <div class="ticket-label">
                UP NEXT
            </div>

            <h2>${ticket.queue_number}</h2>

            <h4>${ticket.first_name} ${ticket.last_name}</h4>

            <p>SR Code: ${ticket.sr_code}</p>

            ${renderDocumentTags(ticket.documents, ticket.queue_type)}

            <small>Status: ${ticket.status}</small>

        </div>

        ${renderQueueTypeBadge(ticket.queue_type)}

    `;
}



/* ==========================================================
   WAITING QUEUE
========================================================== */

function renderWaiting(waiting = []) {

    // Update queue counter
    if (queueCount) {
        queueCount.textContent =
            `${waiting.length} Customer${waiting.length === 1 ? "" : "s"}`;
    }

    if (waiting.length === 0) {

        waitingDiv.innerHTML = `
            <div class="empty">
                No customers waiting.
            </div>
        `;

        return;

    }

    let html = "";

    waiting.forEach((ticket, index) => {

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

    waitingDiv.innerHTML = html;

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