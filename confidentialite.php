<?php
$pageTitle = 'Politique de confidentialité';
require_once __DIR__ . '/includes/header.php';
$siteEmail = getSetting($pdo, 'site_email', '');
$sitePhone = getSetting($pdo, 'site_phone', '');
$siteName = getSetting($pdo, 'site_name', 'Immobilier CI');
$lastUpdate = '28 septembre 2026';
?>
<section class="page-hero">
  <div class="container">
    <div class="breadcrumb"><a href="index">Accueil</a> / Politique de confidentialité</div>
    <h1 data-reveal>Politique de confidentialité</h1>
  </div>
</section>

<div class="container">
  <div class="legal-content" data-reveal>
    <p class="legal-updated">Dernière mise à jour : <?= e($lastUpdate) ?></p>

    <p>La présente politique explique quelles données <?= e($siteName) ?> collecte lorsque vous utilisez notre site web ou notre application mobile, pourquoi, et comment elles sont protégées. Elle s'applique aux visiteurs du site public, aux personnes qui nous contactent, ainsi qu'aux comptes administrateurs de la plateforme.</p>

    <h2>1. Qui sommes-nous ?</h2>
    <p><?= e($siteName) ?> est une plateforme d'annonces immobilières en Côte d'Ivoire (vente et location de villas, appartements, terrains, bureaux, magasins et immeubles). Pour toute question relative à vos données personnelles, vous pouvez nous contacter :</p>
    <ul>
      <?php if ($siteEmail): ?><li>Par e-mail : <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></li><?php endif; ?>
      <?php if ($sitePhone): ?><li>Par téléphone : <a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a></li><?php endif; ?>
    </ul>

    <h2>2. Quelles données nous collectons</h2>
    <p>Nous ne collectons que les données nécessaires au fonctionnement du service :</p>
    <ul>
      <li><strong>Formulaire de contact / demande sur une annonce</strong> : nom complet, adresse e-mail, numéro de téléphone (WhatsApp) et le message que vous rédigez.</li>
      <li><strong>Comptes administrateurs</strong> (pour les membres de notre équipe) : nom, e-mail, téléphone et mot de passe — le mot de passe est stocké sous forme chiffrée (haché), jamais en clair.</li>
      <li><strong>Données techniques</strong> : adresse IP et horodatage, utilisées uniquement pour la sécurité (limiter les tentatives de connexion abusives et le spam sur les formulaires), et un cookie de session strictement nécessaire au fonctionnement du site (connexion à l'espace admin, protection contre les soumissions frauduleuses). Ces cookies techniques ne servent pas à vous suivre ni à faire de la publicité ciblée.</li>
      <li><strong>Photos des annonces</strong> : les images ajoutées aux annonces peuvent être hébergées via un service tiers d'hébergement d'images (Cloudinary) dans le seul but de les afficher rapidement sur le site.</li>
    </ul>
    <p>Nous ne collectons aucune donnée de paiement : le site ne traite aucune transaction financière en ligne.</p>

    <h2>3. Pourquoi nous les utilisons</h2>
    <ul>
      <li>Répondre à vos demandes de contact ou de renseignement sur un bien</li>
      <li>Vous mettre en relation avec l'administrateur en charge d'une annonce (par exemple via WhatsApp)</li>
      <li>Gérer les comptes de notre équipe (administrateurs et super administrateurs) et sécuriser l'accès à l'espace de gestion</li>
      <li>Assurer la sécurité du site (anti-spam, anti-piratage) et conserver un journal d'activité interne des actions effectuées dans l'espace d'administration</li>
    </ul>

    <h2>4. Avec qui vos données sont partagées</h2>
    <p>Vos données ne sont ni vendues, ni louées, ni partagées à des fins publicitaires. Elles peuvent être traitées par des prestataires techniques qui nous aident à faire fonctionner le site, uniquement dans ce cadre :</p>
    <ul>
      <li><strong>Render</strong> — hébergement du site et de la base de données</li>
      <li><strong>Cloudinary</strong> — hébergement des photos des annonces (le cas échéant)</li>
    </ul>
    <p>Ces prestataires peuvent traiter les données sur des serveurs situés hors de Côte d'Ivoire ; nous veillons à ne travailler qu'avec des prestataires offrant un niveau de sécurité adapté.</p>

    <h2>5. Combien de temps nous les conservons</h2>
    <p>Les messages envoyés via le formulaire de contact sont conservés le temps nécessaire à leur traitement puis archivés dans l'espace admin (consultable par notre équipe) jusqu'à suppression manuelle. Les comptes administrateurs sont conservés tant que le compte est actif. Les journaux techniques (tentatives de connexion, limitation anti-spam) sont conservés pour une courte durée à des fins de sécurité.</p>

    <h2>6. Vos droits</h2>
    <p>Conformément à la loi ivoirienne n° 2013-450 relative à la protection des données à caractère personnel, vous disposez d'un droit d'accès, de rectification et de suppression des données vous concernant. Pour exercer ce droit, contactez-nous aux coordonnées indiquées au point 1 ; nous répondrons dans un délai raisonnable.</p>

    <h2>7. Sécurité</h2>
    <p>Nous appliquons des mesures techniques pour protéger vos données : connexion chiffrée (HTTPS), mots de passe stockés uniquement sous forme hachée, accès à l'espace d'administration protégé par mot de passe et limité selon les rôles (administrateur / super administrateur), et une limitation des tentatives de connexion pour prévenir les accès non autorisés.</p>

    <h2>8. Application mobile</h2>
    <p>Notre application mobile affiche le même site web dans une application native ; elle ne collecte pas de données supplémentaires par rapport à celles décrites ci-dessus et ne demande accès à aucune donnée de votre téléphone (contacts, localisation, photos, etc.).</p>

    <h2>9. Modifications de cette politique</h2>
    <p>Cette politique peut être mise à jour occasionnellement, par exemple pour refléter une évolution du site. La date de dernière mise à jour figure en haut de cette page.</p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
