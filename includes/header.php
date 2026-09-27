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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="index" class="logo">Immobilier<span>CI</span></a>
    <nav class="main-nav">
      <a href="index">Accueil</a>
      <a href="annonces">Annonces</a>
      <a href="annonces?listing_type=vente">Vente</a>
      <a href="annonces?listing_type=location">Location</a>
      <a href="contact">Contact</a>
      <?php if (isLoggedIn()): ?>
        <a href="<?= isSuperAdmin() ? 'superadmin/dashboard' : 'admin/dashboard' ?>" class="nav-cta">Mon espace</a>
      <?php else: ?>
        <a href="login" class="nav-cta">Connexion</a>
      <?php endif; ?>
    </nav>
    <button class="nav-toggle" aria-label="Menu">&#9776;</button>
  </div>
</header>
