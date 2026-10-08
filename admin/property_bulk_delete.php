<?php
/**
 * Suppression multiple d'annonces (cases à cocher de admin/properties.php).
 * POST uniquement, protégé par CSRF. Chaque annonce est vérifiée
 * individuellement : un administrateur simple ne peut supprimer que les
 * siennes, le super admin peut tout supprimer.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();
$isSuper = isSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('properties');
}
if (!csrfVerify()) {
    flash('error', 'Session expirée, merci de réessayer.');
    redirect('properties');
}

$rawIds = $_POST['ids'] ?? [];
$ids = [];
foreach ((array)$rawIds as $raw) {
    $n = (int)$raw;
    if ($n > 0) $ids[$n] = $n;
}
$ids = array_values($ids);

if (!$ids) {
    flash('error', 'Aucune annonce sélectionnée.');
    redirect('properties');
}
// Garde-fou : une page de la liste n'affiche que 20 annonces ; au-delà, la requête est anormale.
if (count($ids) > 100) {
    flash('error', 'Trop d\'annonces sélectionnées à la fois (100 maximum).');
    redirect('properties');
}

$select = $pdo->prepare('SELECT id, title, created_by FROM properties WHERE id = ?');
$delete = $pdo->prepare('DELETE FROM properties WHERE id = ?');

$deleted = [];
$denied = 0;
foreach ($ids as $id) {
    $select->execute([$id]);
    $property = $select->fetch();
    if (!$property) continue; // déjà supprimée entre-temps
    if (!$isSuper && $property['created_by'] != $user['id']) {
        $denied++;
        continue;
    }
    deletePropertyFiles($pdo, $id);
    $delete->execute([$id]);
    $deleted[] = '#' . $id;
}

if ($deleted) {
    logActivity($pdo, $user['id'], 'Suppression multiple de ' . count($deleted) . ' annonce(s) : ' . implode(', ', $deleted));
}

$count = count($deleted);
if ($count > 0 && $denied === 0) {
    flash('success', $count === 1 ? '1 annonce supprimée.' : "$count annonces supprimées.");
} elseif ($count > 0) {
    flash('warning', "$count annonce(s) supprimée(s). $denied annonce(s) n'ont pas été supprimées : vous n'en êtes pas le propriétaire.");
} else {
    flash('error', $denied > 0 ? "Vous n'avez pas le droit de supprimer ces annonces." : 'Aucune annonce supprimée.');
}
redirect('properties');
