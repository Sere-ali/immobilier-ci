<?php
$pageTitle = 'Contact';
require_once __DIR__ . '/includes/header.php';

$formError = null;
$formSuccess = false;
$whatsappLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $msg = trim($_POST['message'] ?? '');

    if (!csrfVerify()) {
        $formError = 'Session expirée, merci de réessayer.';
    } elseif ($name === '' || $email === '' || $msg === '' || $phone === '') {
        $formError = 'Merci de remplir tous les champs obligatoires, y compris votre numéro WhatsApp.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Adresse e-mail invalide.';
    } elseif (!isValidWhatsappNumber($phone)) {
        $formError = 'Numéro WhatsApp invalide. Utilisez le format international, ex : +225 07 00 00 00 00.';
    } else {
        // On stocke toujours la version nettoyée des champs, jamais le texte brut envoyé :
        // un champ peut passer la validation de format tout en contenant des caractères indésirables.
        $cleanName = sanitizeText($name, 150);
        $cleanEmail = sanitizeText($email, 190);
        $cleanPhone = sanitizePhoneForStorage($phone);
        $cleanSubject = sanitizeText($subject !== '' ? $subject : 'Message général', 190);
        $cleanMsg = sanitizeText($msg, 2000);
        $stmt = $pdo->prepare('INSERT INTO messages (property_id, full_name, email, phone, subject, message) VALUES (NULL,?,?,?,?,?)');
        $stmt->execute([$cleanName, $cleanEmail, $cleanPhone, $cleanSubject, $cleanMsg]);
        $formSuccess = true;

        $destNumber = getSetting($pdo, 'site_whatsapp', '') ?: getSetting($pdo, 'site_phone', '');
        if ($destNumber && isValidWhatsappNumber($destNumber)) {
            $waMessage = "Bonjour, je suis {$cleanName}. Je viens de vous contacter via le site Immobilier CI. {$cleanMsg}";
            $whatsappLink = waLink($destNumber, $waMessage);
        }
    }
}
?>
<section class="page-hero contact-hero">
  <div class="container">
    <div class="breadcrumb"><a href="index">Accueil</a> / Contact</div>
    <h1 data-reveal>Parlons de votre projet immobilier</h1>
    <p class="contact-hero-sub" data-reveal>Une question, une annonce à publier, une visite à organiser ? Notre équipe vous répond rapidement.</p>
  </div>
</section>

<div class="container">
  <div class="contact-layout">

    <aside class="contact-info-card" data-reveal>
      <div class="contact-info-glow"></div>
      <span class="contact-status"><span class="dot"></span> Disponible aujourd'hui</span>
      <h2>Nos coordonnées</h2>
      <p class="contact-info-lead">Contactez-nous directement, ou passez par le formulaire : nous revenons vers vous sous 24h ouvrées.</p>

      <ul class="contact-info-list" data-reveal-group>
        <li>
          <span class="contact-info-icon">📞</span>
          <div>
            <span class="contact-info-label">Téléphone</span>
            <a href="tel:<?= e(getSetting($pdo, 'site_phone', '')) ?>"><?= e(getSetting($pdo, 'site_phone', '')) ?></a>
          </div>
        </li>
        <?php $siteWhatsapp = getSetting($pdo, 'site_whatsapp', ''); if ($siteWhatsapp): ?>
        <li>
          <span class="contact-info-icon">💬</span>
          <div>
            <span class="contact-info-label">WhatsApp</span>
            <a href="<?= e(waLink($siteWhatsapp, 'Bonjour, je vous contacte depuis le site Immobilier CI.')) ?>" target="_blank" rel="noopener"><?= e($siteWhatsapp) ?></a>
          </div>
        </li>
        <?php endif; ?>
        <li>
          <span class="contact-info-icon">✉️</span>
          <div>
            <span class="contact-info-label">E-mail</span>
            <a href="mailto:<?= e(getSetting($pdo, 'site_email', '')) ?>"><?= e(getSetting($pdo, 'site_email', '')) ?></a>
          </div>
        </li>
        <li>
          <span class="contact-info-icon">📍</span>
          <div>
            <span class="contact-info-label">Adresse</span>
            <span><?= e(getSetting($pdo, 'site_address', '')) ?></span>
          </div>
        </li>
        <li>
          <span class="contact-info-icon">🕒</span>
          <div>
            <span class="contact-info-label">Horaires</span>
            <span>Lun – Sam, 8h – 18h</span>
          </div>
        </li>
      </ul>
    </aside>

    <div class="contact-form-card" data-reveal>
      <?php if ($formSuccess): ?>
        <div class="contact-success">
          <div class="contact-success-icon">✓</div>
          <h3>Message envoyé !</h3>
          <p>Merci, votre message a bien été reçu. Notre équipe vous répondra rapidement.</p>
          <?php if ($whatsappLink): ?>
            <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn-whatsapp" style="margin-bottom:12px">
              <span class="btn-whatsapp-icon">💬</span> Continuer sur WhatsApp
            </a>
          <?php endif; ?>
          <a href="index" class="btn btn-primary">Retour à l'accueil</a>
        </div>
      <?php else: ?>
        <h2>Envoyez-nous un message</h2>
        <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
        <form method="post" data-contact-form class="contact-form">
          <?= csrfField() ?>
          <div class="contact-form-grid">
            <div class="field">
              <label>Nom complet *</label>
              <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>E-mail *</label>
              <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>Numéro WhatsApp *</label>
              <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+225 07 00 00 00 00" required>
            </div>
            <div class="field">
              <label>Sujet</label>
              <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>">
            </div>
          </div>
          <div class="field">
            <label>Message *</label>
            <textarea name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="contact-submit">Envoyer le message <span class="arrow">→</span></button>
        </form>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
