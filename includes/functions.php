<?php
// includes/functions.php — Shared helper functions

/*
|--------------------------------------------------------------------------
| App Configuration
|--------------------------------------------------------------------------
|
| Change this if you move the project.
|
*/
define('APP_URL', '');

/**
 * Build an application URL.
 */
function build_app_url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Sanitize a string for safe HTML output.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a path inside the application.
 */
function redirect(string $path): void
{
    header('Location: ' . build_app_url($path));
    exit;
}

/**
 * Return JSON response.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get_param(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function is_student_logged_in(): bool
{
    return !empty($_SESSION['student_id']);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function is_staff_logged_in(): bool
{
    return !empty($_SESSION['staff_id']);
}

function format_datetime(string $datetime, string $format = 'M d, Y h:i A'): string
{
    return date($format, strtotime($datetime));
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf_token(): bool
{
    return isset($_POST['csrf_token'])
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function get_speed_multiplier(string $speed): float
{
    return match ($speed) {
        'fast' => 0.8,
        'slow' => 1.2,
        default => 1.0,
    };
}