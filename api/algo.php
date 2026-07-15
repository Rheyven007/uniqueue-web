<?php
/**
 * includes/algo.php — Window assignment algorithm
 *
 * A window only qualifies for a ticket if:
 *   1. It's currently open (windows.status = 'open')
 *   2. It serves this ticket's queue type (windows.queue_type = 'both'
 *      OR matches the ticket's type exactly)
 *   3. It can handle EVERY document being requested (window_document
 *      covers the full set) — e.g.:
 *        Window 1 -> doc 1
 *        Window 2 -> doc 2
 *        Window 3 -> doc 1, doc 2
 *      Request [1, 2] -> only Window 3 qualifies
 *      Request [1]    -> Window 1 and Window 3 qualify
 *      Request [2]    -> Window 2 and Window 3 qualify
 *
 * Among qualifying windows, the final pick is load-based: estimated
 * wait = (people currently ahead at that window) x (that window's
 * minutes-per-ticket). Minutes-per-ticket comes from the window's own
 * completed-ticket history when there's enough of it; otherwise it
 * falls back to the manual `windows.speed` rating (fast/normal/slow),
 * since a brand-new or rarely-used window won't have history yet.
 */

/** speed enum -> baseline minutes-per-ticket, used when a window doesn't
 *  have enough completed-ticket history to compute a real average yet. */
const ALGO_SPEED_DEFAULTS = [
    'fast'   => 3.0,
    'normal' => 5.0,
    'slow'   => 8.0,
];

/**
 * Windows in this office that are open, serve this ticket type, and can
 * singlehandedly service ALL given document_ids. This is also what
 * should be shown to the student as "possible windows" pre-assignment.
 *
 * @param string $type 'walkin' or 'appointment'
 * @return array<int, array{id:int, name:string, speed:string}>
 */
function get_eligible_windows(PDO $pdo, int $office_id, array $document_ids, string $type): array
{
    $document_ids = array_values(array_unique(array_map('intval', $document_ids)));
    if (!$document_ids) return [];

    $placeholders = implode(',', array_fill(0, count($document_ids), '?'));
    $needed = count($document_ids);

    $stmt = $pdo->prepare("
        SELECT w.id, w.name, w.speed
        FROM windows w
        JOIN window_document wd ON wd.window_id = w.id
        WHERE w.office_id = ?
          AND w.status = 'open'
          AND (w.queue_type = 'both' OR w.queue_type = ?)
          AND wd.document_id IN ($placeholders)
        GROUP BY w.id, w.name, w.speed
        HAVING COUNT(DISTINCT wd.document_id) = ?
        ORDER BY w.name ASC
    ");
    $stmt->execute(array_merge([$office_id, $type], $document_ids, [$needed]));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** People currently ahead at a window (waiting / called / in_progress). */
function get_window_load(PDO $pdo, int $window_id): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM queue_tickets
        WHERE window_id = ? AND status IN ('waiting','called','in_progress')
    ");
    $stmt->execute([$window_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Minutes-per-ticket for a window: real average from its last 20
 * completed tickets when there's enough history (>= $min_samples),
 * otherwise the manual `speed` rating's baseline. This keeps new/rarely
 * used windows from being scored off a tiny, noisy sample.
 */
function get_window_service_minutes(PDO $pdo, int $window_id, string $speed, int $min_samples = 5): float
{
    $stmt = $pdo->prepare("
        SELECT called_at, done_at
        FROM queue_tickets
        WHERE window_id = ? AND status = 'done'
          AND called_at IS NOT NULL AND done_at IS NOT NULL
        ORDER BY done_at DESC
        LIMIT 20
    ");
    $stmt->execute([$window_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) >= $min_samples) {
        $total = 0;
        foreach ($rows as $r) {
            $total += (strtotime($r['done_at']) - strtotime($r['called_at'])) / 60;
        }
        return $total / count($rows);
    }

    return ALGO_SPEED_DEFAULTS[$speed] ?? ALGO_SPEED_DEFAULTS['normal'];
}

/**
 * Final assignment decision for a new ticket. Scores every eligible
 * window by estimated wait (load x minutes-per-ticket) and returns the
 * lowest. Ties broken alphabetically by window name for determinism.
 *
 * @param string $type 'walkin' or 'appointment'
 * @return int|null window_id, or null if no open window covers this
 *                   exact document + type combination (needs an admin
 *                   to open one / add the mapping).
 */
function pick_best_window(PDO $pdo, int $office_id, array $document_ids, string $type): ?int
{
    $eligible = get_eligible_windows($pdo, $office_id, $document_ids, $type);
    if (!$eligible) return null;

    $best_id = null;
    $best_score = null;

    foreach ($eligible as $w) {
        $load  = get_window_load($pdo, (int)$w['id']);
        $mins  = get_window_service_minutes($pdo, (int)$w['id'], $w['speed']);
        $score = $load * $mins;

        if ($best_score === null || $score < $best_score) {
            $best_score = $score;
            $best_id    = (int)$w['id'];
        }
    }

    return $best_id;
}
