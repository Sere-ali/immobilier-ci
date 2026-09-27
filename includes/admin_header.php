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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="<?= e($root) ?>assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand"><?= logoMark(28) ?>Immobilier<span>CI</span></div>
    <div class="role-tag"><?= $user['role'] === 'superadmin' ? 'Super Administrateur' : 'Administrateur' ?></div>
    <nav>
      <span class="group-label">Gestion</span>
      <a href="<?= e($root) ?>admin/dashboard" class="<?= navActive('dashboard.php', $currentScript) === 'active' && !$inSuperAdmin ? 'active' : '' ?>">📊 Tableau de bord</a>
      <a href="<?= e($root) ?>admin/properties" class="<?= in_array($currentScript, ['properties.php','property_form.php']) && !$inSuperAdmin ? 'active' : '' ?>">🏠 Annonces</a>
      <a href="<?= e($root) ?>admin/messages" class="<?= navActive('messages.php', $currentScript) === 'active' && !$inSuperAdmin ? 'active' : '' ?>">✉️ Messages</a>
      <a href="<?= e($root) ?>admin/profile" class="<?= navActive('profile.php', $currentScript) === 'active' ? 'active' : '' ?>">👤 Mon profil</a>

      <?php if ($user['role'] === 'superadmin'): ?>
        <span class="group-label">Super Admin</span>
        <a href="<?= e($root) ?>superadmin/dashboard" class="<?= navActive('dashboard.php', $currentScript) === 'active' && $inSuperAdmin ? 'active' : '' ?>">🧭 Vue globale</a>
        <a href="<?= e($root) ?>superadmin/users" class="<?= in_array($currentScript, ['users.php','user_form.php']) ? 'active' : '' ?>">🛡️ Administrateurs</a>
        <a href="<?= e($root) ?>superadmin/settings" class="<?= navActive('settings.php', $currentScript) === 'active' ? 'active' : '' ?>">⚙️ Paramètres du site</a>
        <a href="<?= e($root) ?>superadmin/activity_log" class="<?= navActive('activity_log.php', $currentScript) === 'active' ? 'active' : '' ?>">📜 Journal d'activité</a>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <div class="name"><?= e($user['full_name']) ?></div>
      <div class="email"><?= e($user['email']) ?></div>
      <a href="<?= e($root) ?>index">← Voir le site public</a>
      <a href="<?= e($root) ?>admin/logout">Déconnexion</a>
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
