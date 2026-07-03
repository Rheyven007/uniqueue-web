# Queue Management System — Project Structure

## Tech Stack
- **Backend:** PHP
- **Frontend:** HTML, CSS, JavaScript
- **Database:** MySQL (suggested)

---

## Folder & File Structure

```
# Project Structure

```
├── admin
│   ├── capacity
│   │   └── capacity-settings.php
│   ├── counter
│   │   ├── counter-add.php
│   │   ├── counter-edit.php
│   │   ├── counter-list.php
│   │   └── counter-toggle.php
│   ├── document
│   │   ├── document-add.php
│   │   ├── document-delete.php
│   │   ├── document-edit.php
│   │   └── document-list.php
│   ├── feedback
│   │   ├── feedback-analytics.php
│   │   └── feedback-list.php
│   ├── office
│   │   ├── office-add.php
│   │   ├── office-edit.php
│   │   ├── office-list.php
│   │   └── office-toggle.php
│   ├── process
│   │   ├── process-add.php
│   │   ├── process-delete.php
│   │   ├── process-edit.php
│   │   └── process-list.php
│   ├── queue
│   │   └── office-dashboard.php
│   ├── reports
│   │   ├── export-excel.php
│   │   ├── export-pdf.php
│   │   ├── reports-daily.php
│   │   └── reports-performance.php
│   ├── rerquirements
│   │   ├── requirements-add.php
│   │   ├── requirements-delete.php
│   │   ├── requirements-edit.php
│   │   └── requirements-list.php
│   └── dashboard.php
├── api
│   ├── assign-counter.php
│   ├── check-feedback-pending.php
│   ├── get-counters.php
│   ├── get-notifications.php
│   ├── get-queue-status.php
│   ├── submit-feedback.php
│   └── update-queue-status.php
├── assets
│   ├── css
│   │   ├── admin.css
│   │   ├── auth.css
│   │   ├── dashboard.css
│   │   ├── global.css
│   │   ├── notifications.css
│   │   └── queue.css
│   ├── img
│   └── js
│       ├── auth.js
│       ├── dashboard.js
│       ├── document-manage.js
│       ├── feedback.js
│       ├── notifications.js
│       ├── office-dashboard.js
│       ├── office-manage.js
│       ├── process-manage.js
│       ├── queue-monitor.js
│       ├── queue-walkin.js
│       ├── reports.js
│       ├── requirements-manage.js
│       └── smart-assign.js
├── auth
│   ├── login.php
│   ├── logout.php
│   ├── session.php
│   └── validate-srcode.php
├── databases
│   ├── uniqueue_seed.sql
│   └── uniqueue.sql
├── includes
│   ├── db.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── student
│   ├── dashboard.php
│   ├── feedback-submit.php
│   ├── queue-appointment.php
│   ├── queue-status.php
│   ├── queue-ticket.php
│   └── queue-walkin.php
├── index.php
└── Uniqueue.md
```


---

## Module → File Mapping

### 1. Authentication Module
| Feature | Files |
|---|---|
| Login page (SR-Code / email) | `auth/login.php`, `assets/css/auth.css`, `assets/js/auth.js` |
| Validate SR-Code | `auth/validate-srcode.php` |
| Session management | `auth/session.php` |
| Logout | `auth/logout.php` |
| Student dashboard | `student/dashboard.php`, `assets/js/dashboard.js` |

### 2. Office Management Module
| Feature | Files |
|---|---|
| Office CRUD | `admin/office/office-*.php` |
| Process/Event CRUD | `admin/process/process-*.php` |
| Document Type CRUD | `admin/document/document-*.php` |
| Requirements CRUD | `admin/requirements/requirements-*.php` |

### 3. Capacity & Window Management
| Feature | Files |
|---|---|
| Capacity settings | `admin/capacity/capacity-settings.php`, `assets/js/capacity-manage.js` |
| Counter/Window CRUD | `admin/counter/counter-*.php`, `assets/js/counter-manage.js` |

### 4. Queue Management Module
| Feature | Files |
|---|---|
| Walk-in queue | `student/queue-walkin.php`, `assets/js/queue-walkin.js` |
| Appointment queue | `student/queue-appointment.php`, `assets/js/queue-appointment.js` |
| Queue ticket display | `student/queue-ticket.php` |

### 5. Smart Counter Assignment Module
| Feature | Files |
|---|---|
| Counter assignment logic (backend) | `api/assign-counter.php` |
| Counter assignment display (frontend) | `assets/js/smart-assign.js` |

### 6. Real-Time Queue Monitoring
| Feature | Files |
|---|---|
| Queue status page | `student/queue-status.php` |
| Live status polling | `api/get-queue-status.php`, `assets/js/queue-monitor.js` |

### 7. Office Dashboard
| Feature | Files |
|---|---|
| Queue & counter dashboard | `admin/queue/office-dashboard.php` |
| Dashboard refresh | `api/get-counters.php`, `assets/js/office-dashboard.js` |
| Queue call/complete/skip | `api/update-queue-status.php` |

### 8. Reporting & Analytics Module
| Feature | Files |
|---|---|
| Daily reports | `admin/reports/reports-daily.php` |
| Performance reports | `admin/reports/reports-performance.php` |
| PDF export | `admin/reports/export-pdf.php` |
| Excel export | `admin/reports/export-excel.php` |
| Frontend controls | `assets/js/reports.js` |

### 9. Feedback & Customer Satisfaction Module
| Feature | Files |
|---|---|
| Feedback form (student) | `student/feedback-submit.php`, `assets/js/feedback.js` |
| Pending feedback check | `api/check-feedback-pending.php` |
| Feedback AJAX submit | `api/submit-feedback.php` |
| Admin feedback list | `admin/feedback/feedback-list.php` |
| Feedback analytics | `admin/feedback/feedback-analytics.php` |

### 10. Notification Module
| Feature | Files |
|---|---|
| Notification polling | `api/get-notifications.php`, `assets/js/notifications.js` |
| Notification display styles | `assets/css/notifications.css` |

---

## Database Tables (schema.sql)

| Table | Purpose |
|---|---|
| `students` | Student records (SR-Code, name, email) |
| `offices` | Office list (name, status) |  
| `processes` | Events/processes per office |
| `document_types` | Document types linked to offices |
| `requirements` | Requirements per document |
| `counters` | Windows/counters per office |
| `counter_processes` | Which processes each counter can handle |
| `capacity_settings` | Daily/walk-in/appointment capacity per office |
| `queue_tickets` | All generated queue tickets & status |
| `feedback` | Student feedback per transaction |
| `notifications` | Notification log per student |

---

## Notes

- All AJAX endpoints live in `/api/` — they return JSON.
- `auth/session.php` must be `require`d at the top of every protected page.
- `assets/js/queue-monitor.js` uses `setInterval` for auto-refresh of queue status.
- Smart counter assignment logic in `api/assign-counter.php` runs the queue load calculation server-side.
- PDF export can use [TCPDF](https://tcpdf.org/) or [FPDF](http://www.fpdf.org/); Excel export can use [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/).