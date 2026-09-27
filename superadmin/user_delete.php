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

if ($target) {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logActivity($pdo, $currentAdmin['id'], "Suppression du compte {$target['email']}");
    flash('success', 'Compte supprimé.');
} else {
    flash('error', 'Compte introuvable.');
}

redirect('users');
