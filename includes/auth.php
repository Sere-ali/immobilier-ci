<?php
/**
 * Authentification & contrôle d'accès par rôle — Immobilier CI
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function isSuperAdmin(): bool
{
    return isLoggedIn() && $_SESSION['user']['role'] === 'superadmin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(rootPath() . 'login');
    }
}

function requireSuperAdmin(): void
{
    requireLogin();
    if (!isSuperAdmin()) {
        http_response_code(403);
        die('Accès refusé : cette page est réservée au Super Administrateur.');
    }
}

function rootPath(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (preg_match('#/(admin|superadmin)$#', $dir)) {
        return '../';
    }
    return '';
}

function attemptLogin(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'actif' || !password_verify($password, $user['password'])) {
        return null;
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    unset($user['password']);
    $_SESSION['user'] = $user;
    return $user;
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}
