const BASE_URL = "/uniqueue/UNIQUEUE%20v2/admin/queue/";

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

/* ==========================================================
   LOAD QUEUE
========================================================== */

async function loadQueue() {

    currentDiv.innerHTML =
        `<div class="loading-card"></div>`;

    nextDiv.innerHTML =
        `<div class="loading-card"></div>`;

    waitingDiv.innerHTML =
        `<div class="loading-card"></div>`;

    try {

        const response = await fetch(
            BASE_URL + "queue-data.php",
            {
                cache: "no-store",
                credentials: "same-origin"
            }
        );

        const data = await response.json();

        renderStaffInfo(data.staff);
        renderCurrent(data.current);
        renderNext(data.waiting);
        renderWaiting(data.waiting);

    } catch (err) {

        console.error(err);

        currentDiv.innerHTML =
            `<div class="empty">Unable to load current customer.</div>`;

        nextDiv.innerHTML =
            `<div class="empty">Unable to load next customer.</div>`;

        waitingDiv.innerHTML =
            `<div class="empty">Unable to load queue.</div>`;

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

    staffWindowInfo.textContent =
        `${staff.staff_name} — ${windowLabel}`;

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

            <small>
                Status: ${ticket.status}
            </small>

        </div>

    `;

}

/* ==========================================================
   NEXT CUSTOMER
========================================================== */

function renderNext(waiting = []) {

    if (!nextDiv) {
        return;
    }

    const ticket = waiting[0];

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

            <h4>
                ${ticket.first_name} ${ticket.last_name}
            </h4>

            <p>
                SR Code: ${ticket.sr_code}
            </p>

            <small>
                Status: ${ticket.status}
            </small>

        </div>

    `;

}

/* ==========================================================
   WAITING QUEUE
========================================================== */

function renderWaiting(waiting = []) {

    waitingCount.textContent = waiting.length;

    queueCount.textContent =
        `${waiting.length} Customer${waiting.length === 1 ? "" : "s"}`;

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

                <div class="queue-number">
                    ${index + 1}
                </div>

                <div class="queue-details">

                    <div class="queue-title">
                        ${ticket.queue_number}
                    </div>

                    <div class="queue-subtitle">
                        ${ticket.first_name} ${ticket.last_name}
                    </div>

                    <div class="queue-meta">

                        <span class="queue-status waiting">
                            Waiting
                        </span>

                    </div>

                </div>

                <div class="queue-side">

                    <div class="queue-position">
                        #${index + 1}
                    </div>

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

        const response = await fetch(

            BASE_URL + file,

            {
                method: "POST",

                credentials: "same-origin",

                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                }

            }

        );

        const data = await response.json();

        alert(data.message);

        if (file === "queue-done.php") {

            servedToday++;

            servedCount.textContent = servedToday;

        }

        loadQueue();

    } catch (err) {

        console.error(err);

        alert("Something went wrong.");

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

    queueAction("start-service.php");

});

btnDone?.addEventListener("click", () => {

    queueAction("queue-done.php");

});

/* ==========================================================
   INITIALIZE
========================================================== */

loadQueue();

setInterval(loadQueue, 3000);