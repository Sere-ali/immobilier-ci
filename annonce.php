<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = getPDO();

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT p.*, u.full_name AS owner_name, u.whatsapp AS owner_whatsapp FROM properties p LEFT JOIN users u ON u.id = p.created_by WHERE p.slug = ? LIMIT 1');
$stmt->execute([$slug]);
$property = $stmt->fetch();

// Une annonce pas encore approuvée par un super admin reste masquée du public ;
// seuls son créateur et un super admin peuvent la prévisualiser avant validation.
$staffPreview = false;
if ($property && $property['approval_status'] !== 'approuve') {
    $viewer = isLoggedIn() ? currentUser() : null;
    $staffPreview = $viewer && (isSuperAdmin() || $property['created_by'] == $viewer['id']);
    if (!$staffPreview) {
        $property = false;
    }
}

if (!$property) {
    http_response_code(404);
    $pageTitle = 'Bien introuvable';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="section container"><div class="empty-state"><div class="icon">🚫</div><p>Cette annonce n\'existe pas ou a été retirée.</p><a href="annonces" class="btn btn-primary" style="margin-top:16px">Voir les annonces</a></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo->prepare('UPDATE properties SET views = views + 1 WHERE id = ?')->execute([$property['id']]);

$imgStmt = $pdo->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order ASC');
$imgStmt->execute([$property['id']]);
$images = $imgStmt->fetchAll();

$formError = null;
$formSuccess = false;
$whatsappLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $msg = trim($_POST['message'] ?? '');

    if (!csrfVerify()) {
        $formError = 'Session expirée, merci de réessayer.';
    } elseif (countRecentSubmissions($pdo, 'property_contact_form', clientIp()) >= 5) {
        $formError = 'Trop de messages envoyés récemment depuis cette connexion. Merci de réessayer dans quelques minutes.';
    } elseif ($name === '' || $email === '' || $msg === '' || $phone === '') {
        $formError = 'Merci de remplir tous les champs obligatoires, y compris votre numéro WhatsApp.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Adresse e-mail invalide.';
    } elseif (!isValidLocalIvoryCoastPhone($phone)) {
        $formError = 'Numéro WhatsApp invalide : saisissez vos 10 chiffres, sans l\'indicatif (ajouté automatiquement).';
    } else {
        recordSubmission($pdo, 'property_contact_form', clientIp());
        // On stocke toujours la version nettoyée des champs, jamais le texte brut envoyé :
        // un champ peut passer la validation de format tout en contenant des caractères indésirables.
        $cleanName = sanitizeText($name, 150);
        $cleanEmail = sanitizeText($email, 190);
        $cleanPhone = formatIvoryCoastPhoneForStorage($phone);
        $cleanMsg = sanitizeText($msg, 2000);
        $stmt = $pdo->prepare('INSERT INTO messages (property_id, full_name, email, phone, subject, message) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$property['id'], $cleanName, $cleanEmail, $cleanPhone, sanitizeText('Demande sur : ' . $property['title'], 190), $cleanMsg]);
        $formSuccess = true;

        // Numéro WhatsApp de l'administrateur propriétaire de l'annonce, avec repli sur le numéro général du site
        $destNumber = $property['owner_whatsapp'] ?: getSetting($pdo, 'site_whatsapp', '') ?: getSetting($pdo, 'site_phone', '');
        if ($destNumber && isValidWhatsappNumber($destNumber)) {
            $waMessage = "Bonjour, je suis {$cleanName}. Je viens de vous contacter via Immobilier CI au sujet de l'annonce « {$property['title']} » (Réf. {$property['reference']}). {$cleanMsg}";
            $whatsappLink = waLink($destNumber, $waMessage);
        }
    }
}

$pageTitle = $property['title'];
require_once __DIR__ . '/includes/header.php';

$mainImg = !empty($images) ? imageUrl($images[0]['image_path']) : 'https://placehold.co/1000x625/F1ECDD/0B1220?text=Immobilier+CI';
?>
<section class="page-hero" style="padding:30px 0">
  <div class="container">
    <div class="breadcrumb"><a href="index">Accueil</a> / <a href="annonces">Annonces</a> / <?= e($property['title']) ?></div>
  </div>
</section>

<div class="container">
  <?php if ($staffPreview): ?>
    <div class="alert alert-warning" style="margin-bottom:18px">
      👁️ Aperçu : cette annonce n'est pas encore visible du public — statut : <strong><?= e(approvalStatuses()[$property['approval_status']] ?? $property['approval_status']) ?></strong>.
    </div>
  <?php endif; ?>
  <div class="detail-layout">
    <div>
      <div class="gallery-main" style="background-image:url('<?= e($mainImg) ?>')"></div>
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($images as $i => $img): ?>
            <div class="thumb <?= $i === 0 ? 'active' : '' ?>" style="background-image:url('<?= e(imageUrl($img['image_path'])) ?>')" data-full="<?= e(imageUrl($img['image_path'])) ?>"></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="detail-header">
        <div class="ref mono" style="color:var(--ink-soft);font-size:.8rem"><?= e($property['reference']) ?> · <?= e(propertyCategories()[$property['category']] ?? $property['category']) ?></div>
        <h1 style="margin:6px 0"><?= e($property['title']) ?></h1>
        <div class="detail-location">📍 <?= e($property['commune'] ? $property['commune'] . ', ' : '') . e($property['city']) ?></div>
        <div class="detail-price-block">
          <span class="detail-price"><?= formatPrice($property['price']) ?></span>
          <?php if ($property['listing_type'] === 'location'): ?><span class="detail-price-period">/ mois</span><?php endif; ?>
        </div>
      </div>

      <div class="detail-specs">
        <?php if ($property['surface']): ?><div class="spec"><span class="spec-icon">📐</span><div><b class="mono"><?= (int)$property['surface'] ?> m²</b><span>Surface</span></div></div><?php endif; ?>
        <?php if ($property['bedrooms']): ?><div class="spec"><span class="spec-icon">🛏️</span><div><b class="mono"><?= (int)$property['bedrooms'] ?></b><span>Chambres</span></div></div><?php endif; ?>
        <?php if ($property['bathrooms']): ?><div class="spec"><span class="spec-icon">🛁</span><div><b class="mono"><?= (int)$property['bathrooms'] ?></b><span>Salles de bain</span></div></div><?php endif; ?>
        <div class="spec"><span class="spec-icon"><?= $property['listing_type'] === 'vente' ? '🏷️' : '🔑' ?></span><div><b class="mono"><?= $property['listing_type'] === 'vente' ? 'Vente' : 'Location' ?></b><span>Transaction</span></div></div>
      </div>

      <h3>Description</h3>
      <p style="color:var(--ink-soft);line-height:1.7"><?= nl2br(e($property['description'])) ?></p>
    </div>

    <div class="contact-card">
      <h3>Intéressé(e) par ce bien ?</h3>
      <?php if ($formSuccess): ?>
        <div class="alert alert-success">Votre demande a bien été envoyée. Notre équipe vous recontactera rapidement.</div>
        <?php if ($whatsappLink): ?>
          <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn-whatsapp">
            <span class="btn-whatsapp-icon">💬</span> Continuer sur WhatsApp
          </a>
          <p style="font-size:.8rem;color:var(--ink-soft);margin-top:10px">Cliquez pour ouvrir WhatsApp avec votre message déjà prêt à envoyer.</p>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
        <form method="post" data-contact-form>
          <?= csrfField() ?>
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
            <div class="phone-input-group">
              <span class="phone-prefix">+225</span>
              <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-phone-digits
                     value="<?= e($_POST['phone'] ?? '') ?>" placeholder="07 00 00 00 00" required>
            </div>
            <div class="hint">L'administrateur vous répondra directement sur WhatsApp à ce numéro.</div>
          </div>
          <div class="field">
            <label>Message *</label>
            <textarea name="message" rows="4" required>Bonjour, je suis intéressé(e) par ce bien. Merci de me recontacter.</textarea>
          </div>
          <button type="submit">Envoyer la demande</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
