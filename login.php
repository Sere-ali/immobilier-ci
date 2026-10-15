<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
sendSecurityHeaders();
$pdo = getPDO();

if (isLoggedIn()) {
    redirect(isSuperAdmin() ? 'superadmin/dashboard' : 'admin/dashboard');
}

$error = null;
$locked = false;
$mode = (($_GET['creer'] ?? '') === '1' || ($_POST['form'] ?? '') === 'register') ? 'register' : 'login';
$regErrors = [];
$reg = ['full_name' => '', 'email' => '', 'phone' => ''];
$flashMsg = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    // Création de compte ADMINISTRATEUR par la personne elle-même. Le rôle est
    // toujours « admin » : jamais choisi par le formulaire. Seul le super
    // administrateur principal peut nommer un super administrateur.
    $reg['full_name'] = trim($_POST['full_name'] ?? '');
    $reg['email'] = trim($_POST['email'] ?? '');
    $reg['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!csrfVerify()) {
        $regErrors[] = 'Session expirée, merci de réessayer.';
    } elseif (trim($_POST['website'] ?? '') !== '') {
        $regErrors[] = 'Demande refusée.'; // champ piège anti-robots
    } elseif (countRecentSubmissions($pdo, 'admin_signup', clientIp(), 60) >= 5) {
        $regErrors[] = 'Trop de créations de compte depuis cette connexion. Merci de réessayer dans une heure.';
    } else {
        if ($reg['full_name'] === '' || $reg['email'] === '') $regErrors[] = "Le nom et l'e-mail sont obligatoires.";
        if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL)) $regErrors[] = 'Adresse e-mail invalide.';
        if ($reg['phone'] !== '' && !isValidLocalIvoryCoastPhone($reg['phone'])) $regErrors[] = "Numéro de téléphone invalide : 10 chiffres, sans l'indicatif (ajouté automatiquement).";
        if (strlen($password) < 8) $regErrors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if ($password !== $password2) $regErrors[] = 'Les deux mots de passe ne sont pas identiques.';
        if (empty($regErrors)) {
            $fullName = sanitizeText($reg['full_name'], 150);
            $email = sanitizeText($reg['email'], 190);
            $phone = $reg['phone'] !== '' ? formatIvoryCoastPhoneForStorage($reg['phone']) : '';
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $regErrors[] = 'Cette adresse e-mail est déjà utilisée. Connectez-vous ou utilisez une autre adresse.';
            } else {
                recordSubmission($pdo, 'admin_signup', clientIp());
                $pdo->prepare('INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?,?,?,?,?,"actif")')
                    ->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', $phone]);
                $newId = (int)$pdo->lastInsertId();
                logActivity($pdo, $newId, "Création de son compte administrateur ($email) depuis la page de connexion");
                flash('success', 'Compte créé avec succès. Vous pouvez maintenant vous connecter.');
                redirect('login');
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email !== '' && countRecentLoginFailures($pdo, $email) >= 5) {
            $locked = true;
            $error = 'Trop de tentatives échouées. Merci de réessayer dans 15 minutes.';
        } else {
            $user = attemptLogin($pdo, $email, $password);
            if ($user) {
                clearLoginFailures($pdo, $email);
                logActivity($pdo, $user['id'], 'Connexion réussie');
                redirect($user['role'] === 'superadmin' ? 'superadmin/dashboard' : 'admin/dashboard');
            }
            if ($email !== '') {
                recordLoginFailure($pdo, $email);
            }
            $error = 'Identifiants incorrects ou compte désactivé.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Connexion — Immobilier CI</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1D4ED8">
<link rel="icon" href="/assets/img/icons/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/icons/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/css/admin.css?v=<?= assetVersion('assets/css/admin.css') ?>">
</head>
<body>
<div class="login-wrap">

  <aside class="login-visual">
    <div class="login-blob login-blob-1"></div>
    <div class="login-blob login-blob-2"></div>
    <div class="login-visual-content">
      <img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img-lg login-visual-logo">
      <h1>Bienvenue dans<br>votre espace</h1>
      <p>Gérez vos annonces, vos messages et votre équipe en toute simplicité, où que vous soyez.</p>
      <ul class="login-perks">
        <li><span>🏠</span> Publiez et suivez vos annonces</li>
        <li><span>💬</span> Répondez à vos messages en un clic</li>
        <li><span>🛡️</span> Accès sécurisé par rôle</li>
      </ul>
    </div>
  </aside>

  <main class="login-panel">
    <div class="login-card">
      <div class="login-card-head login-visual-logo-mobile">
        <img src="assets/img/logo.png?v=<?= assetVersion('assets/img/logo.png') ?>" alt="Immobilier CI" class="logo-img-lg">
      </div>
      <div class="login-tabs" role="tablist">
        <a href="login" class="<?= $mode === 'login' ? 'active' : '' ?>">Connexion</a>
        <a href="login?creer=1" class="<?= $mode === 'register' ? 'active' : '' ?>">Créer un compte</a>
      </div>

      <?php if ($flashMsg): ?><div class="alert alert-<?= $flashMsg['type'] === 'success' ? 'success' : 'error' ?>"><?= e($flashMsg['message']) ?></div><?php endif; ?>

      <?php if ($mode === 'register'): ?>
      <h2 class="login-card-title">Créer un compte administrateur</h2>
      <p class="sub">Publiez vos annonces. Chaque annonce est validée par notre équipe avant d'être visible.</p>
      <?php foreach ($regErrors as $re): ?><div class="alert alert-error"><?= e($re) ?></div><?php endforeach; ?>
      <form method="post" action="login?creer=1" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="register">
        <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Site web</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field field-anim"><label>Nom complet *</label><input type="text" name="full_name" value="<?= e($reg['full_name']) ?>" required autofocus></div>
        <div class="field field-anim"><label>Adresse e-mail *</label><input type="email" name="email" value="<?= e($reg['email']) ?>" required></div>
        <div class="field field-anim">
          <label>Téléphone / WhatsApp</label>
          <div class="phone-input-group">
            <span class="phone-prefix">+225</span>
            <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="<?= e($reg['phone']) ?>" placeholder="07 00 00 00 00">
          </div>
        </div>
        <div class="field field-anim"><label>Mot de passe * (8 caractères minimum)</label><input type="password" name="password" minlength="8" required></div>
        <div class="field field-anim"><label>Confirmer le mot de passe *</label><input type="password" name="password2" minlength="8" required></div>
        <button type="submit" class="login-submit field-anim">Créer mon compte <span class="arrow">→</span></button>
        <p class="hint" style="margin-top:10px">Ce compte est un compte <strong>Administrateur</strong>. Seul le super administrateur principal peut accorder le rôle de super administrateur.</p>
      </form>
      <?php else: ?>
      <h2 class="login-card-title">Connexion</h2>
      <p class="sub">Espace Admin &amp; Super Admin</p>

      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <?php if (!$locked): ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="field field-anim input-icon input-icon-mail">
          <label>Adresse e-mail</label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="vous@immobilier-ci.ci" required autofocus>
        </div>
        <div class="field field-anim input-icon input-icon-lock">
          <label>Mot de passe</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="login-submit field-anim">Se connecter <span class="arrow">→</span></button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
      <a href="index" class="back-link">← Retour au site public</a>
    </div>
  </main>

</div>
<script src="assets/js/password-toggle.js?v=<?= assetVersion('assets/js/password-toggle.js') ?>" defer></script>
</body>
</html>
