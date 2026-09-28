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

/**
 * Le rôle, le statut (actif/inactif) et les autres attributs d'un compte
 * (ex : is_principal) sont mis en cache dans la session à la connexion. Sans
 * revérification, un compte désactivé — ou dont le rôle change — en cours de
 * session garderait l'accès jusqu'à sa prochaine connexion. On revalide donc
 * ces informations depuis la base à chaque chargement de page admin : un
 * compte désactivé est déconnecté immédiatement et ne voit plus aucune page
 * de l'espace admin/super admin (redirection vers l'écran de connexion).
 */
function refreshCurrentUserFromDb(PDO $pdo): void
{
    if (!isset($_SESSION['user']['id'])) {
        return;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $fresh = $stmt->fetch();

    if (!$fresh || $fresh['status'] !== 'actif') {
        // On retire juste l'utilisateur de la session (sans la détruire
        // entièrement) pour que le message flash ci-dessous survive bien
        // jusqu'à la page de connexion.
        unset($_SESSION['user']);
        flash('error', 'Votre compte a été désactivé. Contactez un super administrateur.');
        redirect(rootPath() . 'login');
    }

    unset($fresh['password']);
    $_SESSION['user'] = $fresh;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(rootPath() . 'login');
    }
    refreshCurrentUserFromDb(getPDO());
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
