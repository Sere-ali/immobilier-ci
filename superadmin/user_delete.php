<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$currentAdmin = currentUser();

$id = (int)($_GET['id'] ?? 0);

if ($id === (int)$currentAdmin['id']) {
    flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    redirect('users');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    flash('error', 'Compte introuvable.');
} elseif ($target['role'] === 'superadmin') {
    // Un super admin ne peut pas supprimer le compte d'un autre super admin.
    flash('error', "Vous ne pouvez pas supprimer le compte d'un autre super administrateur.");
} else {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logActivity($pdo, $currentAdmin['id'], "Suppression du compte {$target['email']}");
    flash('success', 'Compte supprimé.');
}

redirect('users');
