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
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0B1220">
<link rel="icon" href="/assets/img/icons/favicon-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/assets/img/icons/favicon-16.png" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Immobilier CI">
<meta name="mobile-web-app-capable" content="yes">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="container">
    <div class="header-brand">
      <a href="index" class="logo"><img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img logo-img-header"></a>
      <button type="button" id="pwa-install-link" class="pwa-install-badge">
        <span class="pwa-install-badge-icon">📲</span><span class="pwa-install-badge-text">Installer l'app</span>
      </button>
    </div>
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

<div id="pwa-install-toast" class="pwa-install-toast" hidden>
  <span class="pwa-install-toast-spinner"></span>
  <span id="pwa-install-toast-text">Téléchargement en cours. Veuillez patienter…</span>
</div>

<!-- Uniquement pour iPhone/iPad : c'est le SEUL cas où il n'existe aucune
     installation automatique (Apple ne permet pas à Safari de proposer un
     dialogue d'installation). Sur Android, le bouton déclenche directement
     la fenêtre d'installation native de Chrome, sans aucune fenêtre à nous. -->
<div id="pwa-install-tip" class="pwa-install-ios-tip" hidden>
  <div class="pwa-install-ios-tip-box">
    <button type="button" id="pwa-tip-close" class="pwa-install-dismiss" aria-label="Fermer">✕</button>
    <h3>Installer l'application</h3>
    <p>Sur iPhone / iPad (Safari) :</p>
    <ol>
      <li>Appuyez sur l'icône <strong>Partager</strong> <span class="mono">⎋</span> en bas de l'écran</li>
      <li>Choisissez <strong>« Sur l'écran d'accueil »</strong></li>
      <li>Appuyez sur <strong>Ajouter</strong></li>
    </ol>
  </div>
</div>
