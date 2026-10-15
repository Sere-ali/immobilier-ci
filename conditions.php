<?php
$pageTitle = "Conditions d'utilisation";
require_once __DIR__ . '/includes/header.php';
$siteEmail = getSetting($pdo, 'site_email', '');
$sitePhone = getSetting($pdo, 'site_phone', '');
$siteName = getSetting($pdo, 'site_name', 'Immobilier CI');
$lastUpdate = '15 octobre 2026';
?>
<section class="page-hero">
  <div class="container">
    <div class="breadcrumb"><a href="index">Accueil</a> / Conditions d'utilisation</div>
    <h1 data-reveal>Conditions d'utilisation</h1>
  </div>
</section>

<div class="container">
  <div class="legal-content" data-reveal>
    <p class="legal-updated">Dernière mise à jour : <?= e($lastUpdate) ?></p>

    <p>Bienvenue sur <?= e($siteName) ?>. En utilisant ce site, son chat ou son application mobile, vous acceptez les conditions ci-dessous. Si vous n'êtes pas d'accord, merci de ne pas utiliser le service.</p>

    <h2>1. Le service</h2>
    <p><?= e($siteName) ?> est une plateforme d'annonces immobilières en Côte d'Ivoire : vente et location de villas, appartements, terrains, bureaux, magasins et immeubles. Le site permet de consulter des annonces, de contacter notre équipe (formulaire, WhatsApp, chat) et de publier des annonces via notre équipe.</p>

    <h2>2. Les annonces</h2>
    <ul>
      <li>Les annonces sont publiées après validation par notre équipe. Nous faisons de notre mieux pour qu'elles soient exactes et à jour, mais les informations (prix, surface, disponibilité, photos, vidéos) sont fournies par les propriétaires ou les intermédiaires et peuvent évoluer sans préavis.</li>
      <li>Une annonce n'est pas une offre ferme. Avant toute décision, <strong>visitez le bien et vérifiez les documents</strong> (titre de propriété, attestation, bail, etc.).</li>
      <li>Les photos et vidéos sont illustratives. Elles peuvent différer légèrement de la réalité.</li>
    </ul>

    <h2>3. Mise en relation et transactions</h2>
    <p><?= e($siteName) ?> met en relation acheteurs, locataires, propriétaires et agents. Le contrat de vente ou de location est conclu directement entre les parties. Nous vous recommandons de ne jamais verser d'argent avant d'avoir visité le bien, vérifié l'identité de la personne et obtenu un écrit. Le site ne traite aucun paiement en ligne.</p>

    <h2>4. Comportements interdits</h2>
    <ul>
      <li>Publier ou envoyer des informations fausses, trompeuses ou frauduleuses.</li>
      <li>Envoyer du spam, des messages publicitaires non sollicités, des propos injurieux, haineux, illégaux ou contraires aux bonnes mœurs.</li>
      <li>Envoyer via le chat ou les formulaires des fichiers dangereux (virus, logiciels malveillants) ou illégaux.</li>
      <li>Tenter de pirater le site, de contourner sa sécurité, de collecter automatiquement ses données (extraction massive) ou de perturber son fonctionnement.</li>
      <li>Usurper l'identité d'une autre personne ou d'une société.</li>
    </ul>
    <p>Nous pouvons supprimer un message, une annonce ou bloquer l'accès à toute personne qui ne respecte pas ces règles.</p>

    <h2>5. Chat, messages vocaux et fichiers</h2>
    <p>Le chat permet d'écrire, d'envoyer un message vocal ou de joindre un fichier (taille limitée). Ces échanges sont enregistrés et consultables par notre équipe afin de vous répondre et d'assurer la sécurité du service. N'envoyez pas d'informations sensibles inutiles (mots de passe, codes bancaires, etc.). Voir aussi notre <a href="confidentialite">politique de confidentialité</a>.</p>

    <h2>6. Comptes de l'équipe (administrateurs)</h2>
    <p>L'espace d'administration est réservé aux membres de l'équipe autorisés. Chaque titulaire de compte doit garder ses identifiants confidentiels, ne pas les partager, et reste responsable des actions effectuées avec son compte. Les actions dans l'espace d'administration sont consignées dans un journal d'activité.</p>

    <h2>7. Contenus publiés et propriété intellectuelle</h2>
    <p>Le logo, le design, les textes et le code du site appartiennent à <?= e($siteName) ?> et ne peuvent pas être copiés ou réutilisés sans autorisation. En nous transmettant des photos, vidéos ou textes pour une annonce, vous déclarez en détenir les droits et nous autorisez à les afficher sur le site, l'application et nos supports de présentation pour promouvoir le bien.</p>

    <h2>8. Disponibilité du site</h2>
    <p>Nous faisons le maximum pour que le site soit disponible en permanence, mais nous ne pouvons pas garantir l'absence d'interruption (maintenance, panne, problème de connexion ou d'un prestataire d'hébergement).</p>

    <h2>9. Limitation de responsabilité</h2>
    <p>Dans les limites permises par la loi, <?= e($siteName) ?> ne peut pas être tenu responsable des informations fournies par des tiers, d'une transaction conclue entre utilisateurs, ni des dommages indirects liés à l'utilisation du site. Les liens vers des services tiers (par exemple WhatsApp) sont régis par les conditions de ces services.</p>

    <h2>10. Données personnelles</h2>
    <p>Les données que nous collectons et vos droits sont décrits dans notre <a href="confidentialite">politique de confidentialité</a>.</p>

    <h2>11. Modification des conditions</h2>
    <p>Ces conditions peuvent être mises à jour. La date de dernière mise à jour figure en haut de cette page ; continuer à utiliser le site après une modification vaut acceptation.</p>

    <h2>12. Droit applicable et contact</h2>
    <p>Ces conditions sont régies par le droit ivoirien. Pour toute question ou réclamation :</p>
    <ul>
      <?php if ($siteEmail): ?><li>E-mail : <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></li><?php endif; ?>
      <?php if ($sitePhone): ?><li>Téléphone : <a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a></li><?php endif; ?>
      <li>Ou directement via le <a href="contact">formulaire de contact</a>.</li>
    </ul>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
