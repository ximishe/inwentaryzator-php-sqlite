<?php
declare(strict_types=1);

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'helpdesk_sess');
}

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function require_login(): void {
    start_session();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // Refresh user role and active status from database to prevent stale sessions
    try {
        $stmt = db()->prepare("SELECT role, active FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if ($u) {
            if ((int)$u['active'] !== 1) {
                // Account deactivated
                unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
                header('Location: login.php');
                exit;
            }
            $_SESSION['role'] = $u['role'];
        }
    } catch (Exception $e) {
        // Fallback to existing session role if DB is unreachable
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

function is_admin(): bool {
    start_session();
    return ($_SESSION['role'] ?? '') === 'admin';
}

function get_logged_user_id(): int {
    start_session();
    return (int)($_SESSION['user_id'] ?? 0);
}

function get_logged_username(): string {
    start_session();
    return (string)($_SESSION['username'] ?? '');
}
