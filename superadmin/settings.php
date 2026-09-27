<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$user = currentUser();

$fields = ['site_name' => 'Nom du site', 'site_phone' => 'Téléphone', 'site_whatsapp' => 'Numéro WhatsApp (repli général)', 'site_email' => 'E-mail de contact', 'site_address' => 'Adresse', 'site_about' => 'Description / à propos'];

$success = false;
$csrfError = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify()) {
        $csrfError = true;
    } else {
    foreach ($fields as $key => $label) {
        $value = trim($_POST[$key] ?? '');
        if ($key === 'site_whatsapp' || $key === 'site_phone') {
            if ($value !== '' && !isValidLocalIvoryCoastPhone($value)) {
                $errors[] = 'Numéro ' . ($key === 'site_whatsapp' ? 'WhatsApp' : 'de téléphone') . ' invalide : 10 chiffres, sans l\'indicatif (ajouté automatiquement).';
                continue;
            }
            $value = $value !== '' ? formatIvoryCoastPhoneForStorage($value) : '';
        } elseif ($key === 'site_email') {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse e-mail de contact invalide.';
                continue;
            }
            $value = sanitizeText($value, 190);
        } elseif ($key === 'site_about') {
            $value = sanitizeText($value, 1000);
        } else {
            $value = sanitizeText($value, 150);
        }
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
        $stmt->execute([$key, $value, $value]);
    }
    logActivity($pdo, $user['id'], 'Mise à jour des paramètres du site');
    $success = empty($errors);
    }
}

$current = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
    $current[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'Paramètres du site';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head"><h2>Informations générales</h2></div>
  <div class="panel-body">
    <?php if ($success): ?><div class="alert alert-success">Paramètres enregistrés.</div><?php endif; ?>
    <?php if ($csrfError): ?><div class="alert alert-error">Session expirée, merci de réessayer.</div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="form-grid">
        <?php foreach ($fields as $key => $label): ?>
          <div class="field <?= $key === 'site_about' ? 'full' : '' ?>">
            <label><?= e($label) ?></label>
            <?php if ($key === 'site_about'): ?>
              <textarea name="<?= e($key) ?>" rows="4"><?= e($current[$key] ?? '') ?></textarea>
            <?php elseif ($key === 'site_whatsapp' || $key === 'site_phone'): ?>
              <div class="phone-input-group">
                <span class="phone-prefix">+225</span>
                <input type="tel" name="<?= e($key) ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-phone-digits
                       value="<?= e(stripIvoryCoastCountryCode($current[$key] ?? '')) ?>" placeholder="07 00 00 00 00">
              </div>
              <?php if ($key === 'site_whatsapp'): ?><div class="hint">Utilisé quand un administrateur n'a pas renseigné son propre numéro WhatsApp dans son profil.</div><?php endif; ?>
            <?php else: ?>
              <input type="text" name="<?= e($key) ?>" value="<?= e($current[$key] ?? '') ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer les paramètres</button></div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>À propos de la configuration technique</h2></div>
  <div class="panel-body" style="font-size:.88rem;color:var(--ink-soft);line-height:1.7">
    <p>Sur Render, les identifiants de connexion à la base de données sont fournis via des <strong>variables d'environnement</strong> (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS), configurées dans le tableau de bord du service — pas de fichier à modifier.</p>
    <p>Les photos d'annonces sont stockées sur <strong>Cloudinary</strong> (stockage externe persistant) si les variables CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY et CLOUDINARY_API_SECRET sont configurées ; sinon elles sont stockées localement dans <code class="mono"><?= e(UPLOAD_URL) ?></code> (adapté pour un usage local comme WampServer, mais non persistant sur Render sans disque).</p>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
