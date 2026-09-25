<?php
/**
 * En-tête de l'espace d'administration, partagé entre /admin et /superadmin.
 * Attend $pageTitle et éventuellement $pageSubtitle définis avant l'include.
 */
sendSecurityHeaders();
$root = rootPath();
$user = currentUser();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$inSuperAdmin = strpos($_SERVER['SCRIPT_NAME'], '/superadmin/') !== false;

function navActive(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle ?? 'Administration') ?> — Immobilier CI</title>
<link rel="stylesheet" href="<?= e($root) ?>assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand">Immobilier<span>CI</span></div>
    <div class="role-tag"><?= $user['role'] === 'superadmin' ? 'Super Administrateur' : 'Administrateur' ?></div>
    <nav>
      <span class="group-label">Gestion</span>
      <a href="<?= e($root) ?>admin/dashboard.php" class="<?= navActive('dashboard.php', $currentScript) === 'active' && !$inSuperAdmin ? 'active' : '' ?>">📊 Tableau de bord</a>
      <a href="<?= e($root) ?>admin/properties.php" class="<?= in_array($currentScript, ['properties.php','property_form.php']) && !$inSuperAdmin ? 'active' : '' ?>">🏠 Annonces</a>
      <a href="<?= e($root) ?>admin/messages.php" class="<?= navActive('messages.php', $currentScript) === 'active' && !$inSuperAdmin ? 'active' : '' ?>">✉️ Messages</a>
      <a href="<?= e($root) ?>admin/profile.php" class="<?= navActive('profile.php', $currentScript) === 'active' ? 'active' : '' ?>">👤 Mon profil</a>

      <?php if ($user['role'] === 'superadmin'): ?>
        <span class="group-label">Super Admin</span>
        <a href="<?= e($root) ?>superadmin/dashboard.php" class="<?= navActive('dashboard.php', $currentScript) === 'active' && $inSuperAdmin ? 'active' : '' ?>">🧭 Vue globale</a>
        <a href="<?= e($root) ?>superadmin/users.php" class="<?= in_array($currentScript, ['users.php','user_form.php']) ? 'active' : '' ?>">🛡️ Administrateurs</a>
        <a href="<?= e($root) ?>superadmin/settings.php" class="<?= navActive('settings.php', $currentScript) === 'active' ? 'active' : '' ?>">⚙️ Paramètres du site</a>
        <a href="<?= e($root) ?>superadmin/activity_log.php" class="<?= navActive('activity_log.php', $currentScript) === 'active' ? 'active' : '' ?>">📜 Journal d'activité</a>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <div class="name"><?= e($user['full_name']) ?></div>
      <div class="email"><?= e($user['email']) ?></div>
      <a href="<?= e($root) ?>index.php">← Voir le site public</a>
      <a href="<?= e($root) ?>admin/logout.php">Déconnexion</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div>
        <h1><?= e($pageTitle ?? 'Administration') ?></h1>
        <?php if (!empty($pageSubtitle)): ?><div class="subtitle"><?= e($pageSubtitle) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="admin-content">
      <?php $f = getFlash(); if ($f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><span><?= e($f['message']) ?></span></div>
      <?php endif; ?>
