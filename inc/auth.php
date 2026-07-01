<?php
declare(strict_types=1);

const SESSION_NAME = 'helpdesk_sess';

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
