<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $loadedId = null;
    static $user = null;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id === 0) {
        return null;
    }
    if ($loadedId === $id) {
        return $user;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    $loadedId = $id;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function is_logged_in(): bool { return current_user() !== null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function is_customer(): bool { return (current_user()['role'] ?? '') === 'customer'; }

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? url();
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }
}

function require_customer(): void
{
    require_login();
    if (!is_customer()) {
        http_response_code(403);
        exit('Akses khusus pembeli.');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses khusus administrator.');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect(is_admin() ? 'admin/index.php' : 'index.php');
    }
}
