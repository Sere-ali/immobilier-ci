<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pdo = getPDO();
$currentAdmin = currentUser();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editUser = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
    if (!$editUser) { flash('error', 'Utilisateur introuvable.'); redirect('users'); }
    // Un super admin ne peut pas modifier le compte d'un AUTRE super admin (ni
    // son rôle, ni son statut, ni ses identifiants) : ça éviterait qu'un super
    // admin nouvellement nommé ne rétrograde ou ne prenne le contrôle d'un
    // autre compte super admin, y compris celui qui l'a créé.
    if ($editUser['role'] === 'superadmin' && (int)$editUser['id'] !== (int)$currentAdmin['id']) {
        flash('error', "Vous ne pouvez pas modifier le compte d'un autre super administrateur.");
        redirect('users');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfVerify()) {
    $errors[] = 'Session expirée, merci de réessayer.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfVerify()) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $email === '') $errors[] = 'Le nom et l\'e-mail sont obligatoires.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
    if ($phone !== '' && !isValidLocalIvoryCoastPhone($phone)) $errors[] = 'Numéro de téléphone invalide : 10 chiffres, sans l\'indicatif (ajouté automatiquement).';
    if (!in_array($role, ['admin','superadmin'])) $errors[] = 'Rôle invalide.';
    if (!$editUser && strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== '' && strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';

    if (empty($errors)) {
        $fullName = sanitizeText($fullName, 150);
        $email = sanitizeText($email, 190);
        $phone = $phone !== '' ? formatIvoryCoastPhoneForStorage($phone) : '';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$email, $editUser['id'] ?? 0]);
        if ($check->fetch()) $errors[] = 'Cette adresse e-mail est déjà utilisée.';
    }

    if (empty($errors)) {
        if ($editUser) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, role=?, password=? WHERE id=?');
                $stmt->execute([$fullName, $email, $phone, $role, password_hash($password, PASSWORD_DEFAULT), $editUser['id']]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?');
                $stmt->execute([$fullName, $email, $phone, $role, $editUser['id']]);
            }
            logActivity($pdo, $currentAdmin['id'], "Modification du compte #{$editUser['id']} ($email)");
            flash('success', 'Compte mis à jour avec succès.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?,?,?,?,?,"actif")');
            $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $phone]);
            logActivity($pdo, $currentAdmin['id'], "Création du compte $email ($role)");
            flash('success', 'Administrateur créé avec succès.');
        }
        redirect('users');
    }
}

$pageTitle = $editUser ? 'Modifier un administrateur' : 'Nouvel administrateur';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel">
  <div class="panel-head"><h2><?= e($pageTitle) ?></h2></div>
  <div class="panel-body">
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="field"><label>Nom complet *</label><input type="text" name="full_name" value="<?= e($editUser['full_name'] ?? $_POST['full_name'] ?? '') ?>" required></div>
        <div class="field"><label>E-mail *</label><input type="email" name="email" value="<?= e($editUser['email'] ?? $_POST['email'] ?? '') ?>" required></div>
        <div class="field">
          <label>Téléphone</label>
          <div class="phone-input-group">
            <span class="phone-prefix">+225</span>
            <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-phone-digits
                   value="<?= e(stripIvoryCoastCountryCode($editUser['phone'] ?? '')) ?>" placeholder="07 00 00 00 00">
          </div>
        </div>
        <div class="field">
          <label>Rôle *</label>
          <select name="role" required>
            <option value="admin" <?= (($editUser['role'] ?? 'admin') === 'admin') ? 'selected' : '' ?>>Administrateur</option>
            <option value="superadmin" <?= (($editUser['role'] ?? '') === 'superadmin') ? 'selected' : '' ?>>Super Administrateur</option>
          </select>
        </div>
        <div class="field full">
          <label><?= $editUser ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe *' ?></label>
          <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
          <div class="hint">Minimum 8 caractères.</div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editUser ? 'Enregistrer' : 'Créer le compte' ?></button>
        <a href="users" class="btn btn-outline">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
