<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
$stmt->execute([$id]);
$property = $stmt->fetch();

if (!$property || (!isSuperAdmin() && $property['created_by'] != $user['id'])) {
    flash('error', "Vous n'avez pas le droit de supprimer cette annonce.");
    redirect('properties.php');
}

$imgStmt = $pdo->prepare('SELECT image_path FROM property_images WHERE property_id = ?');
$imgStmt->execute([$id]);
foreach ($imgStmt->fetchAll() as $img) {
    // N'essaie de supprimer que les fichiers stockés localement (pas les URL Cloudinary)
    if (!preg_match('#^https?://#i', $img['image_path'])) {
        $path = UPLOAD_DIR . $img['image_path'];
        if (is_file($path)) unlink($path);
    }
}

$pdo->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
logActivity($pdo, $user['id'], "Suppression de l'annonce #$id ({$property['title']})");

flash('success', 'Annonce supprimée.');
redirect('properties.php');
