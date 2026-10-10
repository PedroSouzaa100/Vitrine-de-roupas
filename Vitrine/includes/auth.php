<?php
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

security_headers();

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) return $user;
    $loaded = true;

    if (empty($_SESSION['user_id'])) return $user = null;

    $st = db()->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? AND status = 'active' LIMIT 1");
    $st->execute([$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;

    if (!$user) {
        session_unset();
        session_destroy();
    }

    return $user;
}

function is_logged_in(): bool { return current_user() !== null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function is_seller(): bool { return (current_user()['role'] ?? '') === 'seller'; }

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Faça login para continuar.');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_seller(): array
{
    require_login();
    $user = current_user();
    if ($user['role'] !== 'seller') {
        set_flash('danger', 'Área exclusiva para vendedores.');
        redirect('index.php');
    }
    $seller = get_seller_by_user((int) $user['id']);
    if (!$seller) {
        set_flash('danger', 'Perfil de vendedor não encontrado.');
        redirect('index.php');
    }
    return $seller;
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('danger', 'Acesso restrito ao administrador.');
        redirect('admin/login.php');
    }
}

function get_seller_by_user(int $userId): ?array
{
    $st = db()->prepare("SELECT * FROM sellers WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

function get_seller_store(int $sellerId): ?array
{
    $st = db()->prepare("SELECT * FROM stores WHERE seller_id = ? LIMIT 1");
    $st->execute([$sellerId]);
    return $st->fetch() ?: null;
}

function attempt_login(string $email, string $password): bool
{
    rate_limit('login', 8, 300);

    $st = db()->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        usleep(200000);
        return false;
    }
    if ($user['status'] !== 'active') {
        set_flash('danger', 'Sua conta está bloqueada. Entre em contato com o suporte.');
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}