<?php
// includes/functions.php — Shared helper functions

/**
 * Sanitize a string for safe HTML output.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a given path and exit.
 */
function redirect(string $path): void {
    header("Location: $path");
    exit;
}

/**
 * Return JSON response and exit. For API endpoints.
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if the current request is a POST.
 */
function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get trimmed POST value or default.
 */
function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get trimmed GET value or default.
 */
function get_param(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Check if a student is logged in.
 */
function is_student_logged_in(): bool {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

/**
 * Check if an admin is logged in.
 */
function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function is_staff_logged_in(): bool {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

/**
 * Format a datetime string for display.
 */
function format_datetime(string $datetime, string $format = 'M d, Y h:i A'): string {
    return date($format, strtotime($datetime));
}

/**
 * Generate a CSRF token and store in session.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST.
 */
function validate_csrf_token(): bool {
    return isset($_POST['csrf_token'])
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Get speed multiplier for a window.
 */
function get_speed_multiplier(string $speed): float {
    return match ($speed) {
        'fast' => 0.8,
        'slow' => 1.2,
        default => 1.0, // 'normal'
    };
}
