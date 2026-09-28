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

// Détermine quel lien du menu correspond à la page actuellement affichée,
// pour le mettre en évidence (état "actif") — y compris pour les liens
// Vente/Location qui pointent vers la même page annonces.php avec un filtre différent.
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentListingType = is_string($_GET['listing_type'] ?? null) ? $_GET['listing_type'] : '';
function navLinkClass(string $script, string $currentScript, ?string $listingType = null, string $currentListingType = ''): string
{
    if ($script !== $currentScript) return '';
    if ($listingType !== null && $listingType !== $currentListingType) return '';
    if ($listingType === null && $script === 'annonces.php' && $currentListingType !== '') return '';
    return 'active';
}
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="index" class="logo"><img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img logo-img-header"></a>
    <nav class="main-nav">
      <a href="index" class="<?= navLinkClass('index.php', $currentScript) ?>">Accueil</a>
      <a href="annonces" class="<?= navLinkClass('annonces.php', $currentScript, '', $currentListingType) ?> <?= navLinkClass('annonce.php', $currentScript) ?>">Annonces</a>
      <a href="annonces?listing_type=vente" class="<?= navLinkClass('annonces.php', $currentScript, 'vente', $currentListingType) ?>">Vente</a>
      <a href="annonces?listing_type=location" class="<?= navLinkClass('annonces.php', $currentScript, 'location', $currentListingType) ?>">Location</a>
      <a href="contact" class="<?= navLinkClass('contact.php', $currentScript) ?>">Contact</a>
      <?php if (isLoggedIn()): ?>
        <a href="<?= isSuperAdmin() ? 'superadmin/dashboard' : 'admin/dashboard' ?>" class="nav-cta">Mon espace</a>
      <?php endif; ?>
    </nav>
    <button class="nav-toggle" aria-label="Menu">&#9776;</button>
  </div>
</header>
