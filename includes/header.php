<?php
/**
 * En-tête public. Attend éventuellement $pageTitle défini avant l'include.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
sendSecurityHeaders();
$pdo = getPDO();
$siteName = getSetting($pdo, 'site_name', SITE_NAME);
$pageTitle = $pageTitle ?? $siteName;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
<meta name="description" content="Achat, vente et location de biens immobiliers en Côte d'Ivoire.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="index.php" class="logo">Immobilier<span>CI</span></a>
    <nav class="main-nav">
      <a href="index.php">Accueil</a>
      <a href="annonces.php">Annonces</a>
      <a href="annonces.php?listing_type=vente">Vente</a>
      <a href="annonces.php?listing_type=location">Location</a>
      <a href="contact.php">Contact</a>
      <?php if (isLoggedIn()): ?>
        <a href="<?= isSuperAdmin() ? 'superadmin/dashboard.php' : 'admin/dashboard.php' ?>" class="nav-cta">Mon espace</a>
      <?php else: ?>
        <a href="login.php" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
    <button class="nav-toggle" aria-label="Menu">&#9776;</button>
  </div>
</header>
