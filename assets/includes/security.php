<?php
// ============================================================
// Splitflix - Shared security helpers
// ============================================================
// Provides CSRF protection and flash messages for every page.
// Include this AFTER session_start().
// ============================================================

/**
 * Return the current CSRF token, creating one on first use.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden form input carrying the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Constant-time CSRF token validation.
 */
function csrf_validate($token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Reject a JSON endpoint when the CSRF token is missing or wrong.
 */
function csrf_abort_json(string $message = 'Invalid security token. Please refresh the page and try again.'): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

/**
 * Store a one-shot flash message to display on the next page.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type === 'success' ? 'success' : 'error',
        'message' => $message,
    ];
}

/**
 * Render (and clear) the pending flash message.
 */
function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $class = $flash['type'] === 'success' ? 'flash-success' : 'flash-error';
    return '<div class="flash ' . $class . '">'
        . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
        . '</div>';
}
