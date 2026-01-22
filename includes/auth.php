<?php
session_name('atm_session');
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Пожалуйста, войдите в систему.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', 'Недостаточно прав для этого действия.');
        redirect('index.php');
    }
}

function handle_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $now = time();
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    $inactiveSeconds = $now - $lastActivity;

    if ($inactiveSeconds > 120) {
        $username = $_SESSION['user']['username'] ?? '';
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('warning', "Сессия пользователя {$username} истекла из-за неактивности.");
        return;
    }

    if ($inactiveSeconds > 90) {
        $_SESSION['session_warning'] = true;
    } else {
        unset($_SESSION['session_warning']);
    }

    $_SESSION['last_activity'] = $now;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
}
