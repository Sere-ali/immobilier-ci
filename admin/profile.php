<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pdo = getPDO();
$user = currentUser();

$errors = [];
$successInfo = false;
$successPwd = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    if (!csrfVerify()) {
        $errors[] = 'Session expirée, merci de réessayer.';
    } else {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    if ($fullName === '') {
        $errors[] = 'Le nom complet est obligatoire.';
    } elseif ($whatsapp !== '' && !isValidWhatsappNumber($whatsapp)) {
        $errors[] = 'Numéro WhatsApp invalide (8 à 15 chiffres, avec ou sans indicatif +225).';
    } else {
        $fullName = sanitizeText($fullName, 150);
        $phone = sanitizePhoneForStorage($phone);
        $whatsapp = $whatsapp !== '' ? sanitizePhoneForStorage($whatsapp) : '';
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, whatsapp = ? WHERE id = ?')->execute([$fullName, $phone, $whatsapp, $user['id']]);
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['whatsapp'] = $whatsapp;
        $user = currentUser();
        $successInfo = true;
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!csrfVerify()) {
        $errors[] = 'Session expirée, merci de réessayer.';
    } else {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetch()['password'];

    if (!password_verify($current, $hash)) {
        $errors[] = 'Mot de passe actuel incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($new !== $confirm) {
        $errors[] = 'La confirmation ne correspond pas.';
    } else {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $successPwd = true;
    }
    }
}

$pageTitle = 'Mon profil';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head"><h2>Informations personnelles</h2></div>
  <div class="panel-body">
    <?php if ($successInfo): ?><div class="alert alert-success">Informations mises à jour.</div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="update_info" value="1">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="field"><label>Nom complet</label><input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required></div>
        <div class="field"><label>Téléphone</label><input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
        <div class="field">
          <label>Numéro WhatsApp</label>
          <input type="text" name="whatsapp" value="<?= e($user['whatsapp'] ?? '') ?>" placeholder="+225 07 00 00 00 00">
          <div class="hint">Utilisé pour que les visiteurs intéressés par vos annonces puissent vous écrire directement sur WhatsApp.</div>
        </div>
        <div class="field full"><label>E-mail (non modifiable)</label><input type="text" value="<?= e($user['email']) ?>" disabled></div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Changer le mot de passe</h2></div>
  <div class="panel-body">
    <?php if ($successPwd): ?><div class="alert alert-success">Mot de passe modifié avec succès.</div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="update_password" value="1">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="field full"><label>Mot de passe actuel</label><input type="password" name="current_password" required></div>
        <div class="field"><label>Nouveau mot de passe</label><input type="password" name="new_password" required></div>
        <div class="field"><label>Confirmer le nouveau mot de passe</label><input type="password" name="confirm_password" required></div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Mettre à jour le mot de passe</button></div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
